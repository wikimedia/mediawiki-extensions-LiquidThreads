<?php

namespace MediaWiki\Extension\LiquidThreads\Api;

use LqtView;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\Api\ApiResult;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use stdClass;
use Thread;
use Threads;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

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
	/**
	 * @var (null|string|string[])[] Property definitions
	 */
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

	/** @var array */
	protected $threadIds;

	public function __construct( ApiQuery $query, string $moduleName ) {
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
			$this->addWhere( $this->getDB()->expr( 'thread_type', '!=', Threads::TYPE_DELETED ) );
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
				$fields = self::$propRelations[$name] ?? null;
				self::formatProperty( $name, $fields, $row, $entry );
			}

			if ( isset( $entry['reactions'] ) ) {
				ApiResult::setIndexedTagName( $entry['reactions'], 'reaction' );
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
				static function ( $row ) {
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
				static function ( $row ) {
					return [ $row->thread_id => [ 'id' => $row->thread_id ] ];
				},
				'reply'
			);
		}

		$result->addIndexedTagName( [ 'query', $this->getModuleName() ], 'thread' );
	}

	protected function addSubItems(
		$tableName, $fields, $joinField, $subitemName, callable $handleRow, $tagName
	) {
		if ( !$this->threadIds ) {
			return;
		}
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
		$result = $this->getResult();

		$fields = array_merge( (array)$fields, (array)$joinField );

		$res = $dbr->newSelectQueryBuilder()
			->select( $fields )
			->from( $tableName )
			->where( [
				$joinField => $this->threadIds
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

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
	 * @param stdClass $row
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

		if ( !$article ) {
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

	private static function formatProperty( $name, $fields, $row, &$entry ) {
		if ( $fields === null ) {
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

	private function addPageCond( $prop, $value ) {
		if ( count( $value ) === 1 ) {
			$cond = $this->getPageCond( $prop, $value[0] );
			$this->addWhere( $cond );
		} else {
			$conds = [];
			$dbr = $this->getDB();
			foreach ( $value as $page ) {
				$conds[] = $dbr->andExpr( $this->getPageCond( $prop, $page ) );
			}

			$this->addWhere( $dbr->orExpr( $conds ) );
		}
	}

	private function getPageCond( $prop, $value ) {
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

	private function handleCondition( $prop, $value ) {
		$titleParams = [ 'page', 'root', 'summary' ];
		$fields = self::$propRelations[$prop] ?? null;

		if ( in_array( $prop, $titleParams ) ) {
			// Special cases
			$this->addPageCond( $prop, $value );
		} elseif ( $prop == 'author' ) {
			$this->addWhereFld( 'thread_author_name', $value );
		} elseif ( is_string( $fields ) ) {
			// Common case
			$this->addWhereFld( $fields, $value );
		}
	}

	public function getCacheMode( $params ) {
		if ( $params['render'] ) {
			// Rendering uses the context user
			return 'anon-public-user-private';
		} else {
			return 'public';
		}
	}

	public function getAllowedParams() {
		return [
			'startid' => [
				ParamValidator::PARAM_TYPE => 'integer'
			],
			'endid' => [
				ParamValidator::PARAM_TYPE => 'integer'
			],
			'dir' => [
				ParamValidator::PARAM_TYPE => [
					'newer',
					'older'
				],
				ParamValidator::PARAM_DEFAULT => 'newer',
				ApiBase::PARAM_HELP_MSG => 'api-help-param-direction',
			],
			'showdeleted' => false,
			'limit' => [
				ParamValidator::PARAM_DEFAULT => 10,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => ApiBase::LIMIT_BIG1,
				IntegerDef::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'prop' => [
				ParamValidator::PARAM_DEFAULT => 'id|subject|page|parent|author',
				ParamValidator::PARAM_TYPE => array_keys( self::$propRelations ),
				ParamValidator::PARAM_ISMULTI => true
			],

			'page' => [
				ParamValidator::PARAM_ISMULTI => true
			],
			'author' => [
				ParamValidator::PARAM_ISMULTI => true
			],
			'root' => [
				ParamValidator::PARAM_ISMULTI => true
			],
			'summary' => [
				ParamValidator::PARAM_ISMULTI => true
			],
			'id' => [
				ParamValidator::PARAM_ISMULTI => true
			],
			'render' => false,
			'renderlevel' => [
				ParamValidator::PARAM_DEFAULT => 0,
			],
			'renderthreadpos' => [
				ParamValidator::PARAM_DEFAULT => 1,
			],
			'renderthreadcount' => [
				ParamValidator::PARAM_DEFAULT => 1,
			],
			'rendermaxthreadcount' => [
				ParamValidator::PARAM_DEFAULT => null,
			],
			'rendermaxdepth' => [
				ParamValidator::PARAM_DEFAULT => null,
			],
			'renderstartrepliesat' => [
				ParamValidator::PARAM_DEFAULT => null,
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
