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

namespace pocketmine\entity;

use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\block\Liquid;
use pocketmine\block\Air;
use pocketmine\block\Transparent;
use pocketmine\block\Glass;
use pocketmine\block\GlassPane;

class Zombie extends Monster{
	public const NETWORK_ID = self::ZOMBIE;

	public $width = 0.6;
	public $height = 1.8;
	
	private $attackDelay = 0;
	
	public $moveTarget = null;
	private $directionChangeTicks = 50;

	public function getName() : string{
		return "Zombie";
	}
	
	public function initEntity() : void{
	    parent::initEntity();
	    $this->attributeMap->getAttribute(Attribute::FOLLOW_RANGE)->setDefaultValue(16.0)->resetToDefault();
	    $this->attributeMap->getAttribute(Attribute::ATTACK_DAMAGE)->setDefaultValue(3.0)->resetToDefault();
	    $this->attributeMap->getAttribute(Attribute::MOVEMENT_SPEED)->setDefaultValue(0.19)->resetToDefault();
	}
	
	public function entityBaseTick(int $tickDiff = 1) : bool{
	    
	    //Calculations only
	    if($this->attackDelay < 0){
	        $this->attackDelay = 0;
	    }
	    if($this->attackDelay > 0){
	        $this->attackDelay -= $tickDiff;
	    }
	    
	    $this->directionChangeTicks--;
	    
	    $hasUpdate = parent::entityBaseTick($tickDiff);
	    
	    //Actual commands to the server should only be applied here
	    $time = $this->getLevel() !== null ? $this->getLevel()->getTime() % Level::TIME_FULL : Level::TIME_NIGHT;
	    if(!$this->isOnFire() && ($time < Level::TIME_NIGHT || $time > Level::TIME_SUNRISE)){
	        $this->setOnFire(100);
	    }
	    
	    if(is_null($this->getTargetEntity())){ //Try to find target
	        $nearest = pow($this->attributeMap->getAttribute(Attribute::FOLLOW_RANGE)->getValue(), 2);
	        foreach($this->getLevel()->getNearbyEntities($this->getBoundingBox()->expandedCopy($this->attributeMap->getAttribute(Attribute::FOLLOW_RANGE)->getValue(), $this->attributeMap->getAttribute(Attribute::FOLLOW_RANGE)->getValue(), $this->attributeMap->getAttribute(Attribute::FOLLOW_RANGE)->getValue()), $this) as $potentialTarget){
	            if($potentialTarget instanceof Player && $this->distanceSquared($potentialTarget) <= $nearest){
	                $nearest = $this->distanceSquared($potentialTarget);
	                $this->setTargetEntity($potentialTarget);
	            }
	        }
	        
	        if(($this->moveTarget instanceof Vector3 && $this->distanceSquared($this->moveTarget) <= 1) || $this->directionChangeTicks <= 0){
	            $this->moveTarget = null;
	            $this->directionChangeTicks = random_int(130, 1300);
	        }
	        
	        if(!($this->moveTarget instanceof Vector3)){
	            $randX = random_int($this->getFloorX() - 50, $this->getFloorX() + 50);
	            $randZ = random_int($this->getFloorZ() - 50, $this->getFloorZ() + 50);
	            $randY = $this->getLevel()->getHighestBlockAt($randX, $randZ);
	            $this->moveTarget = new Vector3($randX, $randY, $randZ);
	        }
	        $this->lookAt($this->moveTarget);
	        $normal = sqrt(pow($this->moveTarget->getX() - $this->getX(), 2) + pow($this->moveTarget->getZ() - $this->getZ(), 2));
	        if($normal != 0 && $this->attackTime < 1 && $this->moveTarget->distanceSquared($this) > 1){
	            $this->motion->x = ($this->moveTarget->getX() - $this->getX()) / $normal * $this->attributeMap->getAttribute(Attribute::MOVEMENT_SPEED)->getValue();
	            $this->motion->z = ($this->moveTarget->getZ() - $this->getZ()) / $normal * $this->attributeMap->getAttribute(Attribute::MOVEMENT_SPEED)->getValue();
	        }
	        $obstacle = $this->getLevel()->getBlockAt($this->getFloorX() + $this->motion->getFloorX(), $this->getFloorY(), $this->getFloorZ() + $this->motion->getFloorZ());
	        if(($obstacle instanceof Glass || $obstacle instanceof GlassPane) || (!($obstacle instanceof Liquid) && !($obstacle instanceof Air) && !($obstacle instanceof Transparent))){
	            $this->jump();
	        }
	    } else {
	        if(!$this->getTargetEntity()->isAlive() || $this->getTargetEntity()->isClosed() || $this->distanceSquared($this->getTargetEntity()) > pow($this->attributeMap->getAttribute(Attribute::FOLLOW_RANGE)->getValue(), 2)){
	            $this->setTargetEntity(null);
	        } else {
	            if($this->getTargetEntity()->distanceSquared($this) <= 1 && $this->attackDelay < 1){
	                $this->getTargetEntity()->attack(new EntityDamageByEntityEvent($this, $this->getTargetEntity(), EntityDamageByEntityEvent::CAUSE_ENTITY_ATTACK, $this->attributeMap->getAttribute(Attribute::ATTACK_DAMAGE)->getValue()));
	                $this->attackDelay = 10;
	            }
	            $this->lookAt($this->getTargetEntity());
	            $normal = sqrt(pow($this->getTargetEntity()->getX() - $this->getX(), 2) + pow($this->getTargetEntity()->getZ() - $this->getZ(), 2));
	            if($normal != 0 && $this->attackTime < 1 && $this->getTargetEntity()->distanceSquared($this) > 1){
	                $this->motion->x = ($this->getTargetEntity()->getX() - $this->getX()) / $normal * $this->attributeMap->getAttribute(Attribute::MOVEMENT_SPEED)->getValue();
	                $this->motion->z = ($this->getTargetEntity()->getZ() - $this->getZ()) / $normal * $this->attributeMap->getAttribute(Attribute::MOVEMENT_SPEED)->getValue();
	            }
	            $obstacle = $this->getLevel()->getBlockAt($this->getFloorX() + $this->motion->getFloorX(), $this->getFloorY(), $this->getFloorZ() + $this->motion->getFloorZ());
	            if(($obstacle instanceof Glass || $obstacle instanceof GlassPane) || (!($obstacle instanceof Liquid) && !($obstacle instanceof Air) && !($obstacle instanceof Transparent))){
	                $this->jump();
	            }
	        }
	    }
	    
	    return $hasUpdate;
	}

	public function getDrops() : array{
		$drops = [
			ItemFactory::get(Item::ROTTEN_FLESH, 0, mt_rand(0, 2))
		];

		if(mt_rand(0, 199) < 5){
			switch(mt_rand(0, 2)){
				case 0:
					$drops[] = ItemFactory::get(Item::IRON_INGOT, 0, 1);
					break;
				case 1:
					$drops[] = ItemFactory::get(Item::CARROT, 0, 1);
					break;
				case 2:
					$drops[] = ItemFactory::get(Item::POTATO, 0, 1);
					break;
			}
		}

		return $drops;
	}

	public function getXpDropAmount() : int{
		//TODO: check for equipment and whether it's a baby
		return 5;
	}
}
