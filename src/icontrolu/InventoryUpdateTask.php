<?php

declare(strict_types=1);

namespace icontrolu;

use pocketmine\scheduler\Task;

class InventoryUpdateTask extends Task{
    /** @var iControlU $plugin */
    private $plugin;

    public function __construct(iControlU $plugin){
        $this->plugin = $plugin;
    }

    public function onRun(int $tick) : void{
        foreach($this->plugin->getSessions() as $session){
            $session->syncInventory();
        }
    }
}
