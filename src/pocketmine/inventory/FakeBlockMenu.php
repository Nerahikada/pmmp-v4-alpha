<?php

declare(strict_types=1);

namespace pocketmine\inventory;

use pocketmine\level\Position;

class FakeBlockMenu extends Position implements InventoryHolder{
	
	/** @Inventory */
	protected $inventory;

	public function __construct(Inventory $inventory, Position $pos){
		parent::__construct($pos->x, $pos->y, $pos->z, $pos->level);
		$this->inventory = $inventory;
	}

	public function getInventory(){
		return $this->inventory;
	}
}