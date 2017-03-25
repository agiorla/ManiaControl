<?php

namespace ManiaControl\Callbacks;

use ManiaControl\Callbacks\Models\RecordCallback;
use ManiaControl\Callbacks\Structures\EliteBeginTurnStructure;
use ManiaControl\Callbacks\Structures\ShootMania\DefaultEventStructure;
use ManiaControl\Callbacks\Structures\ShootMania\OnCaptureStructure;
use ManiaControl\Callbacks\Structures\ShootMania\OnHitNearMissArmorEmptyStructure;
use ManiaControl\Callbacks\Structures\ShootMania\OnShootStructure;
use ManiaControl\ManiaControl;

/**
 * Class handling and parsing ShootMania Callbacks
 *
 * @author    ManiaControl Team <mail@maniacontrol.com>
 * @copyright 2014-2017 ManiaControl Team
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class ShootManiaCallbacks implements CallbackListener {
	/*
	 * Constants
	 */
	const CB_TIMEATTACK_ONSTART      = 'TimeAttack_OnStart';
	const CB_TIMEATTACK_ONRESTART    = 'TimeAttack_OnRestart';
	const CB_TIMEATTACK_ONCHECKPOINT = 'TimeAttack_OnCheckpoint';
	const CB_TIMEATTACK_ONFINISH     = 'TimeAttack_OnFinish';

	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl = null;

	/**
	 * Create a new ShootMania Callbacks Instance
	 *
	 * @param ManiaControl    $maniaControl
	 * @param CallbackManager $callbackManager
	 */
	public function __construct(ManiaControl $maniaControl, CallbackManager $callbackManager) {
		$this->maniaControl = $maniaControl;

		// Register for script callbacks
		$callbackManager->registerCallbackListener(Callbacks::SCRIPTCALLBACK, $this, 'handleScriptCallbacks');
	}

	/**
	 * Handle Script Callbacks
	 *
	 * @param string $name
	 * @param mixed  $data
	 */
	public function handleScriptCallbacks($name, $data) {
		if (!$this->maniaControl->getCallbackManager()->callbackListeningExists($name)) {
			return;
		}
		switch ($name) {
			//MP4 New Callbacks
			case Callbacks::SM_EVENTDEFAULT:
				$this->maniaControl->getCallbackManager()->triggerCallback(Callbacks::SM_EVENTDEFAULT, new DefaultEventStructure($this->maniaControl, $data));
				break;
			case Callbacks::SM_ONSHOOT:
				$this->maniaControl->getCallbackManager()->triggerCallback(Callbacks::SM_ONSHOOT, new OnShootStructure($this->maniaControl, $data));
				break;
			case Callbacks::SM_ONHIT:
				$this->maniaControl->getCallbackManager()->triggerCallback(Callbacks::SM_ONHIT, new OnHitNearMissArmorEmptyStructure($this->maniaControl, $data));
				break;
			case Callbacks::SM_ONNEARMISS:
				$this->maniaControl->getCallbackManager()->triggerCallback(Callbacks::SM_ONNEARMISS, new OnHitNearMissArmorEmptyStructure($this->maniaControl, $data));
				break;
			case Callbacks::SM_ONARMOREMPTY:
				$this->maniaControl->getCallbackManager()->triggerCallback(Callbacks::SM_ONARMOREMPTY, new OnHitNearMissArmorEmptyStructure($this->maniaControl, $data));
				break;
			case Callbacks::SM_ONCAPTURE:
				$this->maniaControl->getCallbackManager()->triggerCallback(Callbacks::SM_ONCAPTURE, new OnCaptureStructure($this->maniaControl, $data));
				break;
			//Old Callbacks
			case 'LibXmlRpc_Rankings':
				$this->maniaControl->getServer()->getRankingManager()->updateRankings($data[0]);
				break;
			case 'LibAFK_IsAFK':
				$this->triggerAfkStatus($data[0]);
				break;
			case 'WarmUp_Status':
				$this->maniaControl->getCallbackManager()->triggerCallback(Callbacks::WARMUPSTATUS, $data[0]);
				break;
			case 'Elite_BeginTurn':
				$this->maniaControl->getCallbackManager()->triggerCallback(Callbacks::ELITE_ONBEGINTURN, new EliteBeginTurnStructure($this->maniaControl, $data));
				break;
			case 'Elite_EndTurn':
				$this->maniaControl->getCallbackManager()->triggerCallback(Callbacks::ELITE_ONENDTURN, $data[0]);
				break;
			case 'Joust_SelectedPlayers':
				$this->maniaControl->getCallbackManager()->triggerCallback(Callbacks::JOUST_SELECTEDPLAYERS, $data);
				break;
			case self::CB_TIMEATTACK_ONCHECKPOINT:
				$this->handleTimeAttackOnCheckpoint($name, $data);
				break;
			case self::CB_TIMEATTACK_ONFINISH:
				$this->handleTimeAttackOnFinish($name, $data);
				break;
		}
	}

	/**
	 * Triggers the AFK Status of an Player
	 *
	 * @param string $login
	 */
	private function triggerAfkStatus($login) {
		$player = $this->maniaControl->getPlayerManager()->getPlayer($login);
		$this->maniaControl->getCallbackManager()->triggerCallback(Callbacks::AFKSTATUS, $player);
	}

	/**
	 * Handle TimeAttack OnCheckpoint Callback
	 *
	 * @param string $name
	 * @param array  $data
	 */
	public function handleTimeAttackOnCheckpoint($name, array $data) {
		$login  = $data[0];
		$player = $this->maniaControl->getPlayerManager()->getPlayer($login);
		if (!$player) {
			return;
		}

		// Trigger checkpoint callback
		$checkpointCallback              = new RecordCallback();
		$checkpointCallback->rawCallback = array($name, $data);
		$checkpointCallback->name        = $checkpointCallback::CHECKPOINT;
		$checkpointCallback->setPlayer($player);
		$checkpointCallback->time = (int) $data[1];

		$this->maniaControl->getCallbackManager()->triggerCallback($checkpointCallback);
	}

	/**
	 * Handle TimeAttack OnFinish Callback
	 *
	 * @param string $name
	 * @param array  $data
	 */
	public function handleTimeAttackOnFinish($name, array $data) {
		$login  = $data[0];
		$player = $this->maniaControl->getPlayerManager()->getPlayer($login);
		if (!$player) {
			return;
		}

		// Trigger finish callback
		$finishCallback              = new RecordCallback();
		$finishCallback->rawCallback = array($name, $data);
		$finishCallback->name        = $finishCallback::FINISH;
		$finishCallback->setPlayer($player);
		$finishCallback->time = (int) $data[1];

		$this->maniaControl->getCallbackManager()->triggerCallback($finishCallback);
	}
}
