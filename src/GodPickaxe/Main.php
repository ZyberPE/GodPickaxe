<?php

declare(strict_types=1);

namespace GodPickaxe;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\item\VanillaItems;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\world\Position;
use pocketmine\utils\Config;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\effect\EffectInstance;

class Main extends PluginBase implements Listener {

    private Config $config;
    private array $cooldowns = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {

        if(!$sender instanceof Player) {
            return true;
        }

        if(!$sender->hasPermission("god.pickaxe")) {
            $sender->sendMessage($this->config->get("messages")["no-permission"]);
            return true;
        }

        $cooldownTime = $this->config->get("cooldown-seconds");
        $playerName = $sender->getName();
        $currentTime = time();

        if(isset($this->cooldowns[$playerName])) {
            $remaining = $this->cooldowns[$playerName] - $currentTime;
            if($remaining > 0) {
                $msg = str_replace("{time}", (string)$remaining, $this->config->get("messages")["cooldown"]);
                $sender->sendMessage($msg);
                return true;
            }
        }

        $this->cooldowns[$playerName] = $currentTime + $cooldownTime;

        $pickaxe = VanillaItems::DIAMOND_PICKAXE();

        $pickaxe->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 5));
        $pickaxe->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3));
        $pickaxe->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(), 3));

        $pickaxe->setCustomName($this->config->get("pickaxe")["custom-name"]);
        $pickaxe->setLore($this->config->get("pickaxe")["lore"]);

        $sender->getInventory()->addItem($pickaxe);

        $sender->sendMessage($this->config->get("messages")["success"]);

        return true;
    }

    public function onHold(PlayerItemHeldEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();

        if($item->getCustomName() === $this->config->get("pickaxe")["custom-name"]) {

            $effect = new EffectInstance(VanillaEffects::HASTE(), 999999, 4, false);
            $player->getEffects()->add($effect);

        } else {
            $player->getEffects()->remove(VanillaEffects::HASTE());
        }
    }

    public function onBreak(BlockBreakEvent $event): void {

        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();

        if($item->getCustomName() !== $this->config->get("pickaxe")["custom-name"]) {
            return;
        }

        $block = $event->getBlock();
        $world = $block->getPosition()->getWorld();
        $center = $block->getPosition();

        for($x = -1; $x <= 1; $x++) {
            for($y = -1; $y <= 1; $y++) {
                for($z = -1; $z <= 1; $z++) {

                    $pos = new Position(
                        $center->getX() + $x,
                        $center->getY() + $y,
                        $center->getZ() + $z,
                        $world
                    );

                    $target = $world->getBlock($pos);

                    if(!$target->isAir()) {
                        $world->useBreakOn($pos, $item, $player);
                    }
                }
            }
        }
    }
}
