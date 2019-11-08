<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\inventory;

use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\types\WindowTypes;
use pocketmine\Player;

class EnchantInventory extends ContainerInventory{

	/** @var Position */
	protected $holder;
	/** @var Player */
	protected $player;

	public function __construct(Position $pos, Player $who){
		parent::__construct($pos->asPosition());
		$this->player = $who;
	}

	public function getNetworkType() : int{
		return WindowTypes::ENCHANTMENT;
	}

	public function getName() : string{
		return "Enchantment Table";
	}

	public function getDefaultSize() : int{
		return 2; //1 input, 1 lapis io:14 lapis:15
	}

	/**
	 * This override is here for documentation and code completion purposes only.
	 * @return Position
	 */
	public function getHolder(){
		return $this->holder;
	}

	public function onClose(Player $who) : void{
		parent::onClose($who);

		foreach($who->getInventory()->addItem(...$this->getContents()) as $item){
			$who->dropItem($item);
		}
		$this->clearAll();
	}
}
