<?php

declare(strict_types=1);

namespace GodPickaxe;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

use pocketmine\item\VanillaItems;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\enchantment\EnchantmentInstance;

use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\block\BlockBreakEvent;

use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\effect\EffectInstance;

use pocketmine\math\Facing;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\GameMode;

class Main extends PluginBase implements Listener {

    private array $cooldowns = [];
    private bool $isBreaking = false;

    const GOD_TAG = "god_pickaxe";

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this,$this);
    }

    /* -------------------------
       CREATE PICKAXE
    --------------------------*/

    private function createPickaxe(){

        $item = VanillaItems::DIAMOND_PICKAXE();

        $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(),5));
        $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(),3));
        $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(),3));

        $item->setCustomName($this->getConfig()->get("pickaxe")["custom-name"]);
        $item->setLore($this->getConfig()->get("pickaxe")["lore"]);

        $nbt = $item->getNamedTag();
        $nbt->setByte(self::GOD_TAG,1);
        $item->setNamedTag($nbt);

        return $item;
    }

    private function isGodPickaxe($item): bool{
        return $item->getNamedTag()->getTag(self::GOD_TAG) !== null;
    }

    /* -------------------------
       FIND PLAYER PARTIAL NAME
    --------------------------*/

    private function findPlayer(string $name): ?Player{
        foreach($this->getServer()->getOnlinePlayers() as $player){
            if(stripos($player->getName(),$name) !== false){
                return $player;
            }
        }
        return null;
    }

    /* -------------------------
       COMMAND
    --------------------------*/

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool{

        $msg = $this->getConfig()->get("messages");

        if(!$sender instanceof Player){
            return true;
        }

        if(!isset($args[0])){

            if(!$sender->hasPermission("god.pickaxe")){
                $sender->sendMessage($msg["no-permission"]);
                return true;
            }

            $cooldown = $this->getConfig()->get("cooldown-seconds");
            $time = time();
            $name = $sender->getName();

            if(isset($this->cooldowns[$name])){
                $remaining = $this->cooldowns[$name] - $time;

                if($remaining > 0){
                    $sender->sendMessage(str_replace("{time}",$remaining,$msg["cooldown"]));
                    return true;
                }
            }

            $this->cooldowns[$name] = $time + $cooldown;

            $sender->getInventory()->addItem($this->createPickaxe());
            $sender->sendMessage($msg["success-self"]);
            return true;
        }

        if($args[0] === "give"){

            if(!$sender->hasPermission("god.pickaxe.give")){
                $sender->sendMessage($msg["no-permission-give"]);
                return true;
            }

            if(!isset($args[1])){
                $sender->sendMessage($msg["usage"]);
                return true;
            }

            $target = $this->findPlayer($args[1]);

            if($target === null){
                $sender->sendMessage($msg["player-not-found"]);
                return true;
            }

            $target->getInventory()->addItem($this->createPickaxe());

            $sender->sendMessage(str_replace("{player}",$target->getName(),$msg["success-give"]));
            $target->sendMessage($msg["received"]);

            return true;
        }

        $sender->sendMessage($msg["usage"]);
        return true;
    }

    /* -------------------------
       HASTE
    --------------------------*/

    public function onHold(PlayerItemHeldEvent $event): void{

        $player = $event->getPlayer();
        $item = $event->getItem();

        if($this->isGodPickaxe($item)){

            $effect = new EffectInstance(
                VanillaEffects::HASTE(),
                999999,
                4,
                false
            );

            $player->getEffects()->add($effect);

        }else{
            $player->getEffects()->remove(VanillaEffects::HASTE());
        }
    }

    /* -------------------------
       3x3 BREAK
    --------------------------*/

    public function onBreak(BlockBreakEvent $event): void{

        if($event->isCancelled()) return;
        if($this->isBreaking) return;

        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();

        if(!$this->isGodPickaxe($item)) return;

        $block = $event->getBlock();
        $world = $block->getPosition()->getWorld();
        $center = $block->getPosition();

        $this->isBreaking = true;

        $face = $player->getHorizontalFacing();
        $broken = 0;

        for($y=-1;$y<=1;$y++){
            for($side=-1;$side<=1;$side++){

                $x=0;$z=0;

                switch($face){
                    case Facing::NORTH:
                        $x=$side;$z=-1;
                    break;

                    case Facing::SOUTH:
                        $x=-$side;$z=1;
                    break;

                    case Facing::WEST:
                        $x=-1;$z=-$side;
                    break;

                    case Facing::EAST:
                        $x=1;$z=$side;
                    break;
                }

                $pos = $center->add($x,$y,$z);
                $target = $world->getBlock($pos);

                if(!$target->getBreakInfo()->isBreakable()) continue;

                $world->useBreakOn($pos,$item,$player);
                $broken++;
            }
        }

        if($block->getBreakInfo()->isBreakable()){
            $world->useBreakOn($center,$item,$player);
            $broken++;
        }

        if($broken > 0 && !$player->getGamemode()->equals(GameMode::CREATIVE())){
            $item->applyDamage(1);
            $player->getInventory()->setItemInHand($item);
        }

        $this->isBreaking=false;
    }
}
