<?php

declare(strict_types=1);

namespace pocketmine\inventory;

use pocketmine\entity\Entity;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;
use pocketmine\Player;

class FakeBlockUIComponent extends PlayerUIComponent{
	
	//size,title,id
	public const UI = [1, "UI", -1];
	public const ENCHANT_TABLE = [2, "Enchant", 3];

	private $type;

	public function __construct(PlayerUIInventory $UIInventory, array $type, int $offset, Position $pos){
		parent::__construct($UIInventory, $offset, $type[0]);
		$this->type = $type;
		$this->holder = new FakeBlockMenu($this, $pos);
	}

	public function getHolder() {
		return $this->holder;
	}

	public function open(Player $who) : bool{
		$this->onOpen($who);
		return true;
	}

	public function onOpen(Player $who) : void{
		parent::onOpen($who);
		$pk = new ContainerOpenPacket();
		$pk->windowId = $who->getWindowId($this);
		$pk->type = $this->type[2];
		$holder = $this->getHolder();
		if($holder !== null){
			$pk->x = $holder->getX();
			$pk->y = $holder->getY();
			$pk->z = $holder->getZ();
		}else{
			$pk->x = $pk->y = $pk->z = 0;
		}
		
		$who->dataPacket($pk);

		$this->sendContents($who);

	}

	public function onClose(Player $who) : void{
		$pk = new ContainerClosePacket();
		$pk->windowId = $who->getWindowId($this);
		$who->dataPacket($pk);
		$this->sendContents($who);
		parent::onClose($who);		
	}
}