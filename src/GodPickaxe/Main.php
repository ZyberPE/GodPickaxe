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

class Main extends PluginBase implements Listener {

    private array $cooldowns = [];
    private bool $isBreaking = false;

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /* -------------------------
       COMMAND
    --------------------------*/

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {

        if(!$sender instanceof Player){
            return true;
        }

        if(!$sender->hasPermission("god.pickaxe")){
            $sender->sendMessage($this->getConfig()->get("messages")["no-permission"]);
            return true;
        }

        $cooldownSeconds = $this->getConfig()->get("cooldown-seconds");
        $name = $sender->getName();
        $time = time();

        if(isset($this->cooldowns[$name])){
            $remaining = $this->cooldowns[$name] - $time;
            if($remaining > 0){
                $msg = str_replace("{time}", (string)$remaining, $this->getConfig()->get("messages")["cooldown"]);
                $sender->sendMessage($msg);
                return true;
            }
        }

        $this->cooldowns[$name] = $time + $cooldownSeconds;

        $pickaxe = VanillaItems::DIAMOND_PICKAXE();

        $pickaxe->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 5));
        $pickaxe->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3));
        $pickaxe->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(), 3));

        $pickaxe->setCustomName($this->getConfig()->get("pickaxe")["custom-name"]);
        $pickaxe->setLore($this->getConfig()->get("pickaxe")["lore"]);

        $sender->getInventory()->addItem($pickaxe);

        $sender->sendMessage($this->getConfig()->get("messages")["success"]);
        return true;
    }

    /* -------------------------
       HASTE EFFECT
    --------------------------*/

    public function onHold(PlayerItemHeldEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();

        if($item->getCustomName() === $this->getConfig()->get("pickaxe")["custom-name"]){

            $effect = new EffectInstance(
                VanillaEffects::HASTE(),
                999999,
                4,
                false
            );

            $player->getEffects()->add($effect);

        } else {
            $player->getEffects()->remove(VanillaEffects::HASTE());
        }
    }

    /* -------------------------
       OP PRISON 3x3 FORWARD
    --------------------------*/

    public function onBreak(BlockBreakEvent $event): void {

        if($event->isCancelled()){
            return;
        }

        if($this->isBreaking){
            return; // prevent recursion
        }

        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();

        if($item->getCustomName() !== $this->getConfig()->get("pickaxe")["custom-name"]){
            return;
        }

        $block = $event->getBlock();
        $world = $block->getPosition()->getWorld();
        $center = $block->getPosition();

        $this->isBreaking = true;

        $face = $player->getHorizontalFacing();
        $broken = 0;

        for($y = -1; $y <= 1; $y++){
            for($side = -1; $side <= 1; $side++){

                $x = 0;
                $z = 0;

                switch($face){
                    case Facing::NORTH:
                        $x = $side;
                        $z = -1;
                        break;

                    case Facing::SOUTH:
                        $x = -$side;
                        $z = 1;
                        break;

                    case Facing::WEST:
                        $x = -1;
                        $z = -$side;
                        break;

                    case Facing::EAST:
                        $x = 1;
                        $z = $side;
                        break;
                }

                $pos = $center->add($x, $y, $z);
                $target = $world->getBlock($pos);

                if(!$target->getBreakInfo()->isBreakable()){
                    continue;
                }

                $world->useBreakOn($pos, $item, $player);
                $broken++;
            }
        }

        // Break center block last
        if($block->getBreakInfo()->isBreakable()){
            $world->useBreakOn($center, $item, $player);
            $broken++;
        }

        // Single durability loss
        if($broken > 0){
            $item->applyDamage(1);
            $player->getInventory()->setItemInHand($item);
        }

        $this->isBreaking = false;
    }
}
