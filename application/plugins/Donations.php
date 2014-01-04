<?php
use FML\Controls\Control;
use FML\Controls\Frame;
use FML\Controls\Label;
use FML\Controls\Labels\Label_Button;
use FML\Controls\Labels\Label_Text;
use FML\Controls\Quad;
use FML\Controls\Quads\Quad_Icons128x128_1;
use FML\ManiaLink;
use FML\Script\Script;
use ManiaControl\Admin\AuthenticationManager;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\CallbackManager;
use ManiaControl\Commands\CommandListener;
use ManiaControl\ManiaControl;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Plugins\Plugin;

/**
 * Donation plugin
 *
 * @author steeffeen and Lukas
 */
class DonationPlugin implements CallbackListener, CommandListener, Plugin {
	/**
	 * Constants
	 */
	const ID                              = 3;
	const VERSION                         = 0.1;
	const SETTING_ANNOUNCE_SERVERDONATION = 'Enable Server-Donation Announcements';
	const STAT_PLAYER_DONATIONS           = 'Donated Planets';
	const ACTION_DONATE_VALUE             = 'Donate.DonateValue';

	// DonateWidget Properties
	const MLID_DONATE_WIDGET              = 'DonationPlugin.DonateWidget';
	const SETTING_DONATE_WIDGET_ACTIVATED = 'Donate-Widget Activated';
	const SETTING_DONATE_WIDGET_POSX      = 'Donate-Widget-Position: X';
	const SETTING_DONATE_WIDGET_POSY      = 'Donate-Widget-Position: Y';
	const SETTING_DONATE_WIDGET_WIDTH     = 'Donate-Widget-Size: Width';
	const SETTING_DONATE_WIDGET_HEIGHT    = 'Donate-Widget-Size: Height';
	const SETTING_DONATION_VALUES         = 'Donation Values';

	/**
	 * Private properties
	 */
	/** @var maniaControl $maniaControl */
	private $maniaControl = null;
	private $openBills = array();

	/**
	 *
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		// Register for commands
		$this->maniaControl->commandManager->registerCommandListener('donate', $this, 'command_Donate');
		$this->maniaControl->commandManager->registerCommandListener('pay', $this, 'command_Pay', true);
		$this->maniaControl->commandManager->registerCommandListener('getplanets', $this, 'command_GetPlanets', true);

		// Register for callbacks
		$this->maniaControl->callbackManager->registerCallbackListener(CallbackManager::CB_MP_BILLUPDATED, $this, 'handleBillUpdated');
		$this->maniaControl->callbackManager->registerCallbackListener(CallbackManager::CB_MC_ONINIT, $this, 'handleOnInit');
		$this->maniaControl->callbackManager->registerCallbackListener(PlayerManager::CB_PLAYERJOINED, $this, 'handlePlayerConnect');
		$this->maniaControl->callbackManager->registerCallbackListener(CallbackManager::CB_MP_PLAYERMANIALINKPAGEANSWER, $this, 'handleManialinkPageAnswer');

		// Define player stats
		$this->maniaControl->statisticManager->defineStatMetaData(self::STAT_PLAYER_DONATIONS);

		$this->maniaControl->settingManager->initSetting($this, self::SETTING_DONATE_WIDGET_ACTIVATED, true);
		$this->maniaControl->settingManager->initSetting($this, self::SETTING_DONATE_WIDGET_POSX, 156.);
		$this->maniaControl->settingManager->initSetting($this, self::SETTING_DONATE_WIDGET_POSY, -51.4);
		$this->maniaControl->settingManager->initSetting($this, self::SETTING_DONATE_WIDGET_WIDTH, 6);
		$this->maniaControl->settingManager->initSetting($this, self::SETTING_DONATE_WIDGET_HEIGHT, 6);
		$this->maniaControl->settingManager->initSetting($this, self::SETTING_DONATION_VALUES, "20,50,100,500,1000,2000");

		return true;
	}

	/**
	 *
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
		$this->maniaControl->callbackManager->unregisterCallbackListener($this);
		$this->maniaControl->commandManager->unregisterCommandListener($this);
		unset($this->maniaControl);
	}

	/**
	 *
	 * @see \ManiaControl\Plugins\Plugin::getId()
	 */
	public static function getId() {
		return self::ID;
	}

	/**
	 *
	 * @see \ManiaControl\Plugins\Plugin::getName()
	 */
	public static function getName() {
		return 'Donations Plugin';
	}

	/**
	 *
	 * @see \ManiaControl\Plugins\Plugin::getVersion()
	 */
	public static function getVersion() {
		return self::VERSION;
	}

	/**
	 *
	 * @see \ManiaControl\Plugins\Plugin::getAuthor()
	 */
	public static function getAuthor() {
		return 'steeffeen and kremsy';
	}

	/**
	 *
	 * @see \ManiaControl\Plugins\Plugin::getDescription()
	 */
	public static function getDescription() {
		return 'Plugin offering commands like /donate, /pay and /getplanets and a donation widget.';
	}

	/**
	 * Handle ManiaControl OnInit callback
	 *
	 * @param array $callback
	 */
	public function handleOnInit(array $callback) {
		if($this->maniaControl->settingManager->getSetting($this, self::SETTING_DONATE_WIDGET_ACTIVATED)) {
			$this->displayDonateWidget();
		}
	}

	/**
	 * Handle ManialinkPageAnswer Callback
	 *
	 * @param array $callback
	 */
	public function handleManialinkPageAnswer(array $callback) {
		$actionId    = $callback[1][2];
		$boolSetting = (strpos($actionId, self::ACTION_DONATE_VALUE) === 0);
		if(!$boolSetting) {
			return;
		}

		$login = $callback[1][1];

		$player = $this->maniaControl->playerManager->getPlayer($login);

		$actionArray = explode(".", $callback[1][2]);

		$this->handleDonation($player, intval($actionArray[2]));

	}

	/**
	 * Handle PlayerConnect callback
	 *
	 * @param array $callback
	 */
	public function handlePlayerConnect(array $callback) {
		$player = $callback[1];
		// Display Map Widget
		if($this->maniaControl->settingManager->getSetting($this, self::SETTING_DONATE_WIDGET_ACTIVATED)) {
			$this->displayDonateWidget($player->login);
		}
	}

	/**
	 * Displays the Donate Widget
	 *
	 * @param bool $login
	 */
	public function displayDonateWidget($login = false) {
		$posX              = $this->maniaControl->settingManager->getSetting($this, self::SETTING_DONATE_WIDGET_POSX);
		$posY              = $this->maniaControl->settingManager->getSetting($this, self::SETTING_DONATE_WIDGET_POSY);
		$width             = $this->maniaControl->settingManager->getSetting($this, self::SETTING_DONATE_WIDGET_WIDTH);
		$height            = $this->maniaControl->settingManager->getSetting($this, self::SETTING_DONATE_WIDGET_HEIGHT);
		$values            = $this->maniaControl->settingManager->getSetting($this, self::SETTING_DONATION_VALUES);
		$quadStyle         = $this->maniaControl->manialinkManager->styleManager->getDefaultQuadStyle();
		$quadSubstyle      = $this->maniaControl->manialinkManager->styleManager->getDefaultQuadSubstyle();
		$itemMarginFactorX = 1.3;
		$itemMarginFactorY = 1.2;

		$itemSize = $width;

		$maniaLink = new ManiaLink(self::MLID_DONATE_WIDGET);

		$script = new Script();
		$maniaLink->setScript($script);

		//Donate Menu Icon Frame
		$frame = new Frame();
		$maniaLink->add($frame);
		$frame->setPosition($posX, $posY);

		$backgroundQuad = new Quad();
		$frame->add($backgroundQuad);
		$backgroundQuad->setSize($width * $itemMarginFactorX, $height * $itemMarginFactorY);
		$backgroundQuad->setStyles($quadStyle, $quadSubstyle);

		$iconFrame = new Frame();
		$frame->add($iconFrame);

		$iconFrame->setSize($itemSize, $itemSize);
		$itemQuad = new Quad_Icons128x128_1();
		$itemQuad->setSubStyle($itemQuad::SUBSTYLE_Coppers);
		$itemQuad->setSize($itemSize, $itemSize);
		$iconFrame->add($itemQuad);

		$valueArray = explode(",", $values);

		//Values Menu
		$popoutFrame = new Frame();
		$maniaLink->add($popoutFrame);
		$popoutFrame->setPosition($posX - $itemSize * 0.5, $posY);
		$popoutFrame->setHAlign(Control::RIGHT);
		$popoutFrame->setSize(4 * $itemSize * $itemMarginFactorX, $itemSize * $itemMarginFactorY);

		$quad = new Quad();
		$popoutFrame->add($quad);
		$quad->setHAlign(Control::RIGHT);
		$quad->setStyles($quadStyle, $quadSubstyle);
		$quad->setSize(strlen($values) * 2 + count($valueArray) * 1, $itemSize * $itemMarginFactorY);


		$popoutFrame->add($quad);

		$script->addTooltip($itemQuad, $popoutFrame, Script::OPTION_TOOLTIP_STAYONCLICK);

		//Description Label
		$descriptionFrame = new Frame();
		$maniaLink->add($descriptionFrame);
		$descriptionFrame->setPosition($posX - 50, $posY - 5);
		$descriptionFrame->setHAlign(Control::RIGHT);

		$descriptionLabel = new Label();
		$descriptionFrame->add($descriptionLabel);
		$descriptionLabel->setAlign(Control::LEFT, Control::TOP);
		$descriptionLabel->setSize(40, 4);
		$descriptionLabel->setTextSize(2);
		$descriptionLabel->setVisible(true);
		$descriptionLabel->setTextColor("FF0");

		// Add items
		$x = -2;
		foreach(array_reverse($valueArray) as $value) {
			$label = new Label_Button();
			$popoutFrame->add($label);
			$label->setX($x);
			$label->setHAlign(Control::RIGHT);
			$label->setText('$ff0'.$value . '$700P');
			$label->setTextSize(1.2);
			$label->setAction(self::ACTION_DONATE_VALUE . "." . $value);
			$label->setStyle(Label_Text::STYLE_TextCardSmall);
			$script->addTooltip($label, $descriptionLabel, array(Script::OPTION_TOOLTIP_TEXT => "Donate " . $value . " Planets"));

			$x -= strlen($value) * 2 + 1.7;
		}


		// Send manialink
		$manialinkText = $maniaLink->render()->saveXML();
		$this->maniaControl->manialinkManager->sendManialink($manialinkText, $login);
	}

	/**
	 * Handle /donate command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 * @return bool
	 */
	public function command_Donate(array $chatCallback, Player $player) {
		$text   = $chatCallback[1][2];
		$params = explode(' ', $text);
		if(count($params) < 2) {
			$this->sendDonateUsageExample($player);
			return false;
		}
		$amount = (int)$params[1];
		if(!$amount || $amount <= 0) {
			$this->sendDonateUsageExample($player);
			return false;
		}
		if(count($params) >= 3) {
			$receiver       = $params[2];
			$receiverPlayer = $this->maniaControl->playerManager->getPlayer($receiver);
			$receiverName   = ($receiverPlayer ? $receiverPlayer['NickName'] : $receiver);

		} else {
			$receiver     = '';
			$receiverName = $this->maniaControl->server->getName();
		}

		return $this->handleDonation($player, $amount, $receiver, $receiverName);
	}

	/**
	 * Handles a Player Donate
	 *
	 * @param Player $player
	 * @param        $value
	 */
	private function handleDonation(Player $player, $amount, $receiver = '', $receiverName = false) {
		if(!$receiverName) {
			$receiverName = $this->maniaControl->server->getName();
		}

		$message = 'Donate ' . $amount . ' Planets to $<' . $receiverName . '$>?';
		if(!$this->maniaControl->client->query('SendBill', $player->login, $amount, $message, $receiver)) {
			trigger_error("Couldn't create donation of {$amount} planets from '{$player->login}' for '{$receiver}'. " . $this->maniaControl->getClientErrorText());
			$this->maniaControl->chat->sendError("Creating donation failed.", $player->login);
			return false;
		}
		$bill                   = $this->maniaControl->client->getResponse();
		$this->openBills[$bill] = array(true, $player->login, $receiver, $amount, time());

		return true;
	}

	/**
	 * Handle //pay command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 * @return bool
	 */
	public function command_Pay(array $chatCallback, Player $player) {
		if(!$this->maniaControl->authenticationManager->checkRight($player, AuthenticationManager::AUTH_LEVEL_SUPERADMIN)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return false;
		}
		$text   = $chatCallback[1][2];
		$params = explode(' ', $text);
		if(count($params) < 2) {
			$this->sendPayUsageExample($player);
			return false;
		}
		$amount = (int)$params[1];
		if(!$amount || $amount <= 0) {
			$this->sendPayUsageExample($player);
			return false;
		}
		if(count($params) >= 3) {
			$receiver = $params[2];
		} else {
			$receiver = $player->login;
		}
		$message = 'Payout from $<' . $this->maniaControl->server->getName() . '$>.';
		if(!$this->maniaControl->client->query('Pay', $receiver, $amount, $message)) {
			trigger_error("Couldn't create payout of {$amount} planets by '{$player->login}' for '{$receiver}'. " . $this->maniaControl->getClientErrorText());
			$this->maniaControl->chat->sendError("Creating payout failed.", $player->login);
			return false;
		}
		$bill                   = $this->maniaControl->client->getResponse();
		$this->openBills[$bill] = array(false, $player->login, $receiver, $amount, time());
		return true;
	}

	/**
	 * Handle //getplanets command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 * @return bool
	 */
	public function command_GetPlanets(array $chatCallback, Player $player) {
		if(!$this->maniaControl->authenticationManager->checkRight($player, AuthenticationManager::AUTH_LEVEL_ADMIN)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return false;
		}
		if(!$this->maniaControl->client->query('GetServerPlanets')) {
			trigger_error("Couldn't retrieve server planets. " . $this->maniaControl->getClientErrorText());
			return false;
		}
		$planets = $this->maniaControl->client->getResponse();
		$message = "This Server has {$planets} Planets!";
		return $this->maniaControl->chat->sendInformation($message, $player->login);
	}

	/**
	 * Handle bill updated callback
	 *
	 * @param array $callback
	 * @return bool
	 */
	public function handleBillUpdated(array $callback) {
		$billId = $callback[1][0];
		if(!array_key_exists($billId, $this->openBills)) {
			return false;
		}
		$billData = $this->openBills[$billId];
		$login    = $billData[1];
		$receiver = $billData[2];
		switch($callback[1][1]) {
			case 4:
			{
				// Payed
				$donation = $billData[0];
				$amount   = $billData[3];
				if($donation) {
					$player = $this->maniaControl->playerManager->getPlayer($login);

					// Donation
					if(strlen($receiver) > 0) {
						// To player
						$message = "Successfully donated {$amount} to '{$receiver}'!";
						$this->maniaControl->chat->sendSuccess($message, $login);
					} else {
						// To server
						if($this->maniaControl->settingManager->getSetting($this, self::SETTING_ANNOUNCE_SERVERDONATION, true)) {
							$message = '$<' . $player->nickname . '$> donated ' . $amount . ' Planets! Thanks.';
						} else {
							$message = 'Donation successful! Thanks.';
						}
						$this->maniaControl->chat->sendSuccess($message, $login);
						$this->maniaControl->statisticManager->insertStat(self::STAT_PLAYER_DONATIONS, $player, $this->maniaControl->server->getServerId(), $amount);
					}
				} else {
					// Payout
					$message = "Successfully payed out {$amount} to '{$receiver}'!";
					$this->maniaControl->chat->sendSuccess($message, $login);
				}
				unset($this->openBills[$billId]);
				break;
			}
			case 5:
			{
				// Refused
				$message = 'Transaction cancelled.';
				$this->maniaControl->chat->sendError($message, $login);
				unset($this->openBills[$billId]);
				break;
			}
			case 6:
			{
				// Error
				$this->maniaControl->chat->sendError($callback[1][2], $login);
				unset($this->openBills[$billId]);
				break;
			}
		}
		return true;
	}

	/**
	 * Send an usage example for /donate to the player
	 *
	 * @param Player $player
	 * @return boolean
	 */
	private function sendDonateUsageExample(Player $player) {
		$message = "Usage Example: '/donate 100'";
		return $this->maniaControl->chat->sendChat($message, $player->login);
	}

	/**
	 * Send an usage example for /pay to the player
	 *
	 * @param Player $player
	 * @return boolean
	 */
	private function sendPayUsageExample(Player $player) {
		$message = "Usage Example: '/pay 100 login'";
		return $this->maniaControl->chat->sendChat($message, $player->login);
	}
}
