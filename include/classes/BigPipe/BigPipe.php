<?php
#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#
# BigPipe main class                         [Thomas Lange <code@nerdmind.de>] #
#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#
#                                                                              #
# The BigPipe main class is responsible for sorting and rendering the pagelets #
# and their associated resources. This class also provides methods to turn off #
# the pipeline mode or turn on the debugging mode.                             #
#                                                                              #
#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#
namespace BigPipe;

class BigPipe {
	private static $debugging = FALSE;
	private static $enabled   = TRUE;
	private static $pagelets  = [];

	#===============================================================================
	# Enable or disable the pipeline mode
	#===============================================================================
	public static function enabled($change = NULL) {
		if($change !== NULL) {
			self::$enabled = (bool) $change;
		}

		return self::$enabled;
	}

	#===============================================================================
	# Return if debugging is enabled or change
	#===============================================================================
	public static function debugging($change = NULL): bool {
		if($change !== NULL) {
			self::$debugging = (bool) $change;
		}

		return self::$debugging;
	}

	#===============================================================================
	# Insert pagelet into queue
	#===============================================================================
	public static function enqueue(Pagelet $Pagelet) {
		self::$pagelets[spl_object_hash($Pagelet)] = $Pagelet;
	}

	#===============================================================================
	# Remove pagelet from queue
	#===============================================================================
	public static function dequeue(Pagelet $Pagelet) {
		unset(self::$pagelets[spl_object_hash($Pagelet)]);
	}

	#===============================================================================
	# Prints a single pagelet response
	#===============================================================================
	private static function singleResponse(Pagelet $Pagelet, $last = FALSE) {
		if(self::debugging()) {
			self::addDebugPhaseDoneJS($Pagelet);

			array_map('self::addDebugPhaseDoneJS', $Pagelet->getResources()[Resource::TYPE_STYLESHEET]);
			array_map('self::addDebugPhaseDoneJS', $Pagelet->getResources()[Resource::TYPE_JAVASCRIPT]);

			usleep(rand(125, 175) * 2000);
		}

		$pageletJSON = $Pagelet->getStructure();

		if($last) {
			$pageletJSON['IS_LAST'] = TRUE;
		}

		$pageletHTML = removeLineBreaksAndTabs($Pagelet->getHTML());
		$pageletHTML = str_replace('--', '&#45;&#45;', $pageletHTML);

		$pageletJSON = json_encode($pageletJSON, (self::debugging() ? JSON_PRETTY_PRINT : NULL));

		echo "<code hidden id=\"_{$Pagelet->getID()}\"><!-- {$pageletHTML} --></code>\n";
		echo "<script>BigPipe.onPageletArrive({$pageletJSON}, document.getElementById(\"_{$Pagelet->getID()}\"));</script>\n\n";
	}

	#===============================================================================
	# Sends output buffer so far as possible towards user
	#===============================================================================
	public static function flushOutputBuffer() {
		ob_flush(); flush();
	}

	#===============================================================================
	# Renders all remaining pagelets from the queue in the appropriate order
	#===============================================================================
	public static function render() {
		self::flushOutputBuffer();

		$i = 0;

		$pagelets_ordered = [];

		foreach(self::$pagelets as $Pagelet) {
			$pagelets_ordered[$Pagelet->getPriority()][] = $Pagelet;
		}

		krsort($pagelets_ordered);

		if(!empty($pagelets_ordered)) {
			$pagelets = call_user_func_array('array_merge', $pagelets_ordered);

			foreach($pagelets as $Pagelet) {
				if(!self::enabled()) {
					foreach($Pagelet->getResources()[Resource::TYPE_STYLESHEET] as $Resource) {
						echo "{$Resource->renderHTML()}\n";
					}

					foreach($Pagelet->getResources()[Resource::TYPE_JAVASCRIPT] as $Resource) {
						echo "{$Resource->renderHTML()}\n";
					}

					foreach($Pagelet->getJSCode() as $JSCode) {
						echo "<script>{$JSCode}</script>\n";
					}
				}

				else {
					self::singleResponse($Pagelet, (count(self::$pagelets) === ++$i));
					self::flushOutputBuffer();
				}
			}
		}
	}

	#===============================================================================
	# Add PhaseDoneJS for debugging Pagelet and Resource
	#===============================================================================
	private static function addDebugPhaseDoneJS($Instance) {
		$objpath = str_replace('\\', '|', get_class($Instance));

		if($Instance instanceof Pagelet) {
			$message = "console.log(\"%%c[{$objpath}]%%c#(%%c%s%%c): PhaseDoneJS for phase: %s\", \"font-weight:bold\", \"color:#666\", \"color:#008B45\", \"color:#666\")";

			$Instance->addPhaseDoneJS($Instance::PHASE_INIT,    sprintf($message, $Instance->getID(), 'INIT'));
			$Instance->addPhaseDoneJS($Instance::PHASE_LOADCSS, sprintf($message, $Instance->getID(), 'LOADCSS'));
			$Instance->addPhaseDoneJS($Instance::PHASE_HTML,    sprintf($message, $Instance->getID(), 'HTML'));
			$Instance->addPhaseDoneJS($Instance::PHASE_LOADJS,  sprintf($message, $Instance->getID(), 'LOADJS'));
			$Instance->addPhaseDoneJS($Instance::PHASE_DONE,    sprintf($message, $Instance->getID(), 'DONE'));

			return $Instance;
		}

		if($Instance instanceof Resource) {
			$message = "console.log(\"[{$objpath}]%%c#(%%c%s%%c): PhaseDoneJS for phase: %s\", \"color:#666\", \"color:#008B45\", \"color:#666\")";

			$Instance->addPhaseDoneJS($Instance::PHASE_INIT, sprintf($message, $Instance->getID(), 'INIT'));
			$Instance->addPhaseDoneJS($Instance::PHASE_LOAD, sprintf($message, $Instance->getID(), 'LOAD'));
			$Instance->addPhaseDoneJS($Instance::PHASE_DONE, sprintf($message, $Instance->getID(), 'DONE'));

			return $Instance;
		}
	}
}
?>