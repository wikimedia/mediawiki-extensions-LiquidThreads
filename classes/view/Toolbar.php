<?php

abstract class LiquidThreadsToolbar extends LiquidThreadsFormatter {
	/**
	 * Gets the HTML for this toolbar.
	 * @param $object The object that the toolbar is for.
	 * @param $context A context object, usually for a related formatter.
	 * @return HTML result
	 */
	public function getHTML( $object, $context = null ) {
		$commands = $this->getCommands( $object, $context );
		
		$formattedCommands = $this->formatCommands( $commands );
		
		$list = Xml::tags( 'ul', array( 'class' => $this->getCSSClass() ),
			$formattedCommands );
			
		return $list;
	}
	
	/**
	 * Gets the class to be applied to the toolbar.
	 */
	protected function getCSSClass() {
		return 'lqt-toolbar';
	}

	/**
	 * Returns the commands to be shown in this toolbar.
	 * @param $object The object that the toolbar is for.
	 * @param $context A context object, usually for a related formatter.
	 * @return An array suitable for LiquidThreadsToolbar::formatCommands
	 */
	abstract protected function getCommands( $object, $context = null );
	
	/**
	 * Formats a list of toolbar commands.
	 * @param $commands Associative array of commands.
	 * @return HTML
	 * @see LiquidThreadsPostFormatter::formatCommand
	 */
	public function formatCommands( $commands ) {
		$result = array();
		foreach ( $commands as $key => $command ) {
			$thisCommand = $this->formatCommand( $command );

			$thisCommand = Xml::tags(
				'li',
				array( 'class' => 'lqt-command lqt-command-' . $key ),
				$thisCommand
			);

			$result[] = $thisCommand;
		}
		return join( ' ', $result );
	}

	/**
	 * Formats a toolbar command
	 * @param $command Associative array describing this command
	 *     Valid keys:
	 *         label: The text to show for this command.
	 *         href: The URL to link to.
	 *         enabled: Whether or not this command is enabled.
	 *         tooltip: If specified, the tooltip to show for this command.
	 *         icon: If specified, an icon is shown.
	 *         showlabel: Whether or not to show the label. Default: on.
	 * @param $icon_divs Boolean: If false, do not insert <divs> to style with an icon.
	 * @return HTML: Command formatted in a <div>
	 */
	public function formatCommand( $command, $icon_divs = true ) {
		$label = $command['label'];
		$href = $command['href'];
		$enabled = $command['enabled'];
		$tooltip = isset( $command['tooltip'] ) ? $command['tooltip'] : '';

		if ( isset( $command['icon'] ) ) {
			$icon = Xml::tags( 'div', array( 'title' => $label,
					'class' => 'lqt-command-icon' ), '&#160;' );
			if ( $icon_divs ) {
				if ( !empty( $command['showlabel'] ) ) {
					$label = $icon . '&#160;' . $label;
				} else {
					$label = $icon;
				}
			} else {
				if ( empty( $command['showlabel'] ) ) {
					$label = '';
				}
			}
		}

		if ( $enabled ) {
			$thisCommand = Xml::tags( 'a', array( 'href' => $href, 'title' => $tooltip ),
					$label );
		} else {
			$thisCommand = Xml::tags( 'span', array( 'class' => 'lqt_command_disabled',
						'title' => $tooltip ), $label );
		}

		return $thisCommand;
	}
	
}
