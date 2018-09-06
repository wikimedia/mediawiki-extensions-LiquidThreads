<?php
/**
 * LiquidThreads API Query module
 *
 * Data that can be returned:
 * - ID
 * - Subject
 * - "host page"
 * - parent
 * - ancestor
 * - creation time
 * - modification time
 * - author
 * - summary article ID
 * - "root" page ID
 * - type
 * - replies
 * - reactions
 */

class ApiQueryLQTThreads extends ApiQueryBase {
	// Property definitions
	public static $propRelations = [
		'id' => 'thread_id',
		'subject' => 'thread_subject',
		'page' => [
			'namespace' => 'thread_article_namespace',
			'title' => 'thread_article_title'
		],
		'parent' => 'thread_parent',
		'ancestor' => 'thread_ancestor',
		'created' => 'thread_created',
		'modified' => 'thread_modified',
		'author' => [
			'id' => 'thread_author_id',
			'name' => 'thread_author_name'
		],
		'summaryid' => 'thread_summary_page',
		'rootid' => 'thread_root',
		'type' => 'thread_type',
		'signature' => 'thread_signature',
		'reactions' => null, // Handled elsewhere
		'replies' => null, // Handled elsewhere
	];

	/** @var array **/
	protected $threadIds;

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'th' );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$prop = array_flip( $params['prop'] );
		$result = $this->getResult();
		$this->addTables( 'thread' );
		$this->addFields( 'thread_id' );

		foreach ( self::$propRelations as $name => $fields ) {
			// Pass a straight array rather than one with string
			// keys, to be sure that merging it into other added
			// arrays doesn't mess stuff up
			$this->addFieldsIf( array_values( (array)$fields ), isset( $prop[$name] ) );
		}

		// Check for conditions
		$conditionFields = [ 'page', 'root', 'summary', 'author', 'id' ];
		foreach ( $conditionFields as $field ) {
			if ( isset( $params[$field] ) ) {
				$this->handleCondition( $field, $params[$field] );
			}
		}

		$this->addOption( 'LIMIT', $params['limit'] + 1 );
		$this->addWhereRange( 'thread_id', $params['dir'],
			$params['startid'], $params['endid'] );

		if ( !$params['showdeleted'] ) {
			$delType = $this->getDB()->addQuotes( Threads::TYPE_DELETED );
			$this->addWhere( "thread_type != $delType" );
		}

		if ( $params['render'] ) {
			// All fields
			$allFields = [
				'thread_id', 'thread_root', 'thread_article_namespace',
				'thread_article_title', 'thread_summary_page', 'thread_ancestor',
				'thread_parent', 'thread_modified', 'thread_created', 'thread_type',
				'thread_editedness', 'thread_subject', 'thread_author_id',
				'thread_author_name', 'thread_signature'
			];

			$this->addFields( $allFields );
		}

		$res = $this->select( __METHOD__ );

		$ids = [];
		$count = 0;
		foreach ( $res as $row ) {
			if ( ++$count > $params['limit'] ) {
				// We've had enough
				$this->setContinueEnumParameter( 'startid', $row->thread_id );
				break;
			}

			$entry = [];
			foreach ( $prop as $name => $nothing ) {
				$fields = self::$propRelations[$name];
				self::formatProperty( $name, $fields, $row, $entry );
			}

			if ( isset( $entry['reactions'] ) ) {
				$result->setIndexedTagName( $entry['reactions'], 'reaction' );
			}

			// Render if requested
			if ( $params['render'] ) {
				$this->renderThread( $row, $params, $entry );
			}

			$ids[$row->thread_id] = $row->thread_id;

			if ( $entry ) {
				$fit = $result->addValue( [ 'query',
						$this->getModuleName() ],
					$row->thread_id, $entry );
				if ( !$fit ) {
					$this->setContinueEnumParameter( 'startid', $row->thread_id );
					break;
				}
			}
		}

		$this->threadIds = $ids;

		if ( isset( $prop['reactions'] ) ) {
			$this->addSubItems(
				'thread_reaction',
				'*',
				'tr_thread',
				'reactions',
				function ( $row ) {
					return [ "{$row->tr_user}_{$row->tr_type}" => [
						'type' => $row->tr_type,
						'user-id' => $row->tr_user,
						'user-name' => $row->tr_user_text,
						'value' => $row->tr_value,
					] ];
				},
				'reaction'
			);
		}

		if ( isset( $prop['replies'] ) ) {
			$this->addSubItems(
				'thread',
				'thread_id',
				'thread_parent',
				'replies',
				function ( $row ) {
					return [ $row->thread_id => [ 'id' => $row->thread_id ] ];
				},
				'reply'
			);
		}

		$result->addIndexedTagName( [ 'query', $this->getModuleName() ], 'thread' );
	}

	protected function addSubItems(
		$tableName, $fields, $joinField, $subitemName, /*callable*/ $handleRow, $tagName
	) {
		$dbr = wfGetDB( DB_REPLICA );
		$result = $this->getResult();

		$fields = array_merge( (array)$fields, (array)$joinField );

		$res = $dbr->select(
			$tableName,
			$fields,
			[
				$joinField => $this->threadIds
			],
			__METHOD__
		);

		foreach ( $res as $row ) {
			$output = $handleRow( $row );

			$path = [
				'query',
				$this->getModuleName(),
				$row->$joinField,
			];

			$result->addValue(
				$path,
				$subitemName,
				$output
			);

			$result->addIndexedTagName( array_merge( $path, [ $subitemName ] ), $tagName );
		}
	}

	/**
	 * @suppress SecurityCheck-XSS Due to $oldOutputText
	 * @param array $row
	 * @param array $params
	 * @param array &$entry
	 */
	protected function renderThread( $row, $params, &$entry ) {
		// Set up OutputPage
		$out = $this->getOutput();
		$oldOutputText = $out->getHTML();
		$out->clearHTML();

		// Setup
		$thread = Thread::newFromRow( $row );
		$article = $thread->root();

		if ( ! $article ) {
			return;
		}

		$title = $article->getTitle();
		$user = $this->getUser();
		$request = $this->getRequest();
		$view = new LqtView( $out, $article, $title, $user, $request );

		// Parameters
		$view->threadNestingLevel = $params['renderlevel'];

		$renderpos = $params['renderthreadpos'];
		$rendercount = $params['renderthreadcount'];

		$options = [];
		if ( isset( $params['rendermaxthreadcount'] ) ) {
			$options['maxCount'] = $params['rendermaxthreadcount'];
		}
		if ( isset( $params['rendermaxdepth'] ) ) {
			$options['maxDepth'] = $params['rendermaxdepth'];
		}
		if ( isset( $params['renderstartrepliesat'] ) ) {
			$options['startAt' ] = $params['renderstartrepliesat'];
		}

		$view->showThread( $thread, $renderpos, $rendercount, $options );

		$result = $out->getHTML();
		$out->clearHTML();
		$out->addHTML( $oldOutputText );

		$entry['content'] = $result;
	}

	static function formatProperty( $name, $fields, $row, &$entry ) {
		if ( is_null( $fields ) ) {
			$entry[$name] = [];
		} elseif ( !is_array( $fields ) ) {
			// Common case.
			$entry[$name] = $row->$fields;
		} elseif ( $name == 'page' ) {
			// Special cases
			$nsField = $fields['namespace'];
			$tField = $fields['title'];
			$title = Title::makeTitle( $row->$nsField, $row->$tField );
			ApiQueryBase::addTitleInfo( $entry, $title, 'page' );
		} else {
			// Complicated case.
			foreach ( $fields as $part => $field ) {
				$entry[$name][$part] = $row->$field;
			}
		}
	}

	function addPageCond( $prop, $value ) {
		if ( count( $value ) === 1 ) {
			$cond = $this->getPageCond( $prop, $value[0] );
			$this->addWhere( $cond );
		} else {
			$conds = [];
			foreach ( $value as $page ) {
				$cond = $this->getPageCond( $prop, $page );
				$conds[] = $this->getDB()->makeList( $cond, LIST_AND );
			}

			$cond = $this->getDB()->makeList( $conds, LIST_OR );
			$this->addWhere( $cond );
		}
	}

	function getPageCond( $prop, $value ) {
		$fieldMappings = [
			'page' => [
				'namespace' => 'thread_article_namespace',
				'title' => 'thread_article_title',
			],
			'root' => [ 'id' => 'thread_root' ],
			'summary' => [ 'id' => 'thread_summary_id' ],
		];

		// Split.
		$t = Title::newFromText( $value );
		$cond = [];
		foreach ( $fieldMappings[$prop] as $type => $field ) {
			switch ( $type ) {
				case 'namespace':
					$cond[$field] = $t->getNamespace();
					break;
				case 'title':
					$cond[$field] = $t->getDBkey();
					break;
				case 'id':
					$cond[$field] = $t->getArticleID();
					break;
				default:
					ApiBase::dieDebug( __METHOD__, "Unknown condition type $type" );
			}
		}
		return $cond;
	}

	function handleCondition( $prop, $value ) {
		$titleParams = [ 'page', 'root', 'summary' ];
		$fields = self::$propRelations[$prop];

		if ( in_array( $prop, $titleParams ) ) {
			// Special cases
			$this->addPageCond( $prop, $value );
		} elseif ( $prop == 'author' ) {
			$this->addWhereFld( 'thread_author_name', $value );
		} elseif ( !is_array( $fields ) ) {
			// Common case
			$this->addWhereFld( $fields, $value );
		}
	}

	public function getCacheMode( $params ) {
		if ( $params['render'] ) {
			// Rendering uses $wgUser
			return 'anon-public-user-private';
		} else {
			return 'public';
		}
	}

	public function getAllowedParams() {
		return [
			'startid' => [
				ApiBase::PARAM_TYPE => 'integer'
			],
			'endid' => [
				ApiBase::PARAM_TYPE => 'integer'
			],
			'dir' => [
				ApiBase::PARAM_TYPE => [
					'newer',
					'older'
				],
				ApiBase::PARAM_DFLT => 'newer',
				ApiBase::PARAM_HELP_MSG => 'api-help-param-direction',
			],
			'showdeleted' => false,
			'limit' => [
				ApiBase::PARAM_DFLT => 10,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'prop' => [
				ApiBase::PARAM_DFLT => 'id|subject|page|parent|author',
				ApiBase::PARAM_TYPE => array_keys( self::$propRelations ),
				ApiBase::PARAM_ISMULTI => true
			],

			'page' => [
				ApiBase::PARAM_ISMULTI => true
			],
			'author' => [
				ApiBase::PARAM_ISMULTI => true
			],
			'root' => [
				ApiBase::PARAM_ISMULTI => true
			],
			'summary' => [
				ApiBase::PARAM_ISMULTI => true
			],
			'id' => [
				ApiBase::PARAM_ISMULTI => true
			],
			'render' => false,
			'renderlevel' => [
				ApiBase::PARAM_DFLT => 0,
			],
			'renderthreadpos' => [
				ApiBase::PARAM_DFLT => 1,
			],
			'renderthreadcount' => [
				ApiBase::PARAM_DFLT => 1,
			],
			'rendermaxthreadcount' => [
				ApiBase::PARAM_DFLT => null,
			],
			'rendermaxdepth' => [
				ApiBase::PARAM_DFLT => null,
			],
			'renderstartrepliesat' => [
				ApiBase::PARAM_DFLT => null,
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&list=threads&thpage=Talk:Main_Page'
				=> 'apihelp-query+threads-example-1',
			'action=query&list=threads&thid=1|2|3|4&thprop=id|subject|modified'
				=> 'apihelp-query+threads-example-2',
		];
	}
}
