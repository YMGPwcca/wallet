<?php
/*
*    __          __   _ _      _   
*    \ \        / /  | | |    | |  
*     \ \  /\  / /_ _| | | ___| |_ 
*      \ \/  \/ / _` | | |/ _ \ __|
*       \  /\  / (_| | | |  __/ |_ 
*        \/  \/ \__,_|_|_|\___|\__|
*			   SpermLord/DevNTNghia
*/

namespace Wallet;

use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\Player;
use pocketmine\event\Listener;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;
use Wallet\libs\muqsit\invmenu\InvMenu;
use Wallet\libs\muqsit\invmenu\InvMenuHandler;
use pocketmine\item\{Item, ItemFactory};
use pocketmine\inventory\transaction\action\SlotChangeAction;
use onebone\economyapi\EconomyAPI;
use pocketmine\math\Vector3;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\scheduler\Task;
use pocketmine\level\Level;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\inventory\CraftItemEvent;

class dropTask extends Task {
	private $level;
	private $target;
	private $plugin;

	private $time = 5;

	function __construct(Main $main, Level $level, Player $target) {
		$this->plugin = $main;
		$this->level = $level;
		$this->target = $target;
	}

	public function onRun(int $currentTick) {
		$this->time--;
		if ($this->time < 1){
			$this->plugin->getScheduler()->cancelTask($this->getTaskId());
		}
		$x = $this->target->getX();
		$y = $this->target->getY();
		$z = $this->target->getZ();
		$pos = new Vector3($x, $y + 2, $z);
		$moneyItems = [Item::EMERALD, Item::GOLD_NUGGET, Item::DIAMOND, Item::GOLD_INGOT];
		$this->level->dropItem($pos, Item::get($moneyItems[mt_rand(0, count($moneyItems) - 1)])->setCustomName("SPERMLORD"));
	}
}

class NganHang {
	/** @var InvMenu */
	private $nganhangM;

	/** @var InvMenu */
	private $chuyentienM;

	private $plugin;

	private $tag = TextFormat::GOLD . "[" . TextFormat::GREEN . "Wallet" . TextFormat::GOLD . "] " . TextFormat::WHITE;

	public function __construct(Player $player, Main $main) {
		$this->plugin = $main;
		$money = EconomyAPI::getInstance();
		$this->nganhangM = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST)
			->readonly()
			->setName("Ví tiền của bạn")
			->setListener([$this, "nganhangF"]);
		$this->nganhangM->getInventory()->setItem(13, Item::get(Item::EMERALD)->setCustomName("Hiện có: " . $money->myMoney($player) . ".000VNĐ"));
		$this->nganhangM->getInventory()->setItem(37, Item::get(Item::GOLD_NUGGET)->setCustomName("Chuyển tiền"));
		$this->nganhangM->getInventory()->setItem(39, Item::get(Item::DIAMOND)->setCustomName("Nạp tiền"));
		$this->nganhangM->getInventory()->setItem(41, Item::get(Item::GOLD_INGOT)->setCustomName("Rút tiền"));
		$this->nganhangM->getInventory()->setItem(43, Item::get(Item::SIGN)->setCustomName("Ngân hàng"));
	}

	public function nganhangF(Player $player, Item $iA, Item $iB, SlotChangeAction $action): bool {
		if ($iA->getId() == Item::GOLD_NUGGET) {
			$player->removeWindow($action->getInventory());
			$this->chuyentienF($player);
			return false;
		}
		else if ($iA->getId() == Item::SIGN) {
			$player->removeWindow($action->getInventory());
			$player->sendMessage($this->tag . "Đang ném bạn tới Ngân hàng");
			return false;
		}
		else if ($iA->getId() == Item::DIAMOND) {
			$player->removeWindow($action->getInventory());
			$this->naptienF($player);
			return false;
		}
		else if ($iA->getId() == Item::GOLD_INGOT) {
			$player->removeWindow($action->getInventory());
			$this->ruttienF($player);
			return false;
		}
		return true;
	}
	
	public function ruttienF(Player $player) {
		$player->removeAllWindows();
		$formapi = Server::getInstance()->getPluginManager()->getPlugin("FormAPI");
		$form = $formapi->createCustomForm(function (Player $player, Array $data = null) {
			$money = EconomyAPI::getInstance();
			$default = $money->myMoney($player);
			$amount = (int)$data[0];

			if ($amount === null) {
				$player->sendMessage($this->tag . "Bạn phải nhập số tiền cần rút!");
				return;
			}
			else {
				if (!is_numeric($amount)) {
					$player->sendMessage($this->tag . "Số tiền cần rút phải là số!");
					return;
				}
			}
			
			if ($amount > $money->myMoney($player)) {
				$player->sendMessage($this->tag . "Số tiền cần rút lớn hơn số dư trong tài khoản!");
				return;
			}
			
			$money->reduceMoney($player, $amount);

			if ($default > $money->myMoney($player)) {
				if ($amount > 500000) {
					$player->sendMessage($this->tag . "Bạn không thể rút > 500.000.000VNĐ. Hãy ra ngân hàng để rút số tiền lớn hơn.");
					return;
				}
				if (!$player->getInventory()->canAddItem(Item::get(Item::DIRT))) {
					$player->sendMessage($this->tag . "Hãy để ít nhất 5 khoảng trống ở inventory");
					$money->addMoney($player, $amount);
					return;
				}
				while ($amount >= 500) {
					$item = ItemFactory::get(Item::NETHER_WART, 0, 1);
					$item->setCustomName("500.000VNĐ");
					if (!$player->getInventory()->canAddItem($item)) {
						$player->sendMessage($this->tag . "Không thể rút thêm 500.000VNĐ vì không còn chỗ chứa trong kho đồ, số tiền còn lại đã được đưa lại vào ví");
						$money->addMoney($player, $amount);
						return;
					}
					$player->getInventory()->addItem($item);
					$amount -= 500;
				}
				while ($amount >= 200) {
					$item = ItemFactory::get(Item::MAGMA_CREAM, 0, 1);
					$item->setCustomName("200.000VNĐ");
					if (!$player->getInventory()->canAddItem($item)) {
						$player->sendMessage($this->tag . "Không thể rút thêm 200.000VNĐ vì không còn chỗ chứa trong kho đồ, số tiền còn lại đã được đưa lại vào ví");
						$money->addMoney($player, $amount);
						return;
					}
					$player->getInventory()->addItem($item);
					$amount -= 200;
				}
				while ($amount >= 100) {
					$item = ItemFactory::get(Item::LEATHER, 0, 1);
					$item->setCustomName("100.000VNĐ");
					if (!$player->getInventory()->canAddItem($item)) {
						$player->sendMessage($this->tag . "Không thể rút thêm 100.000VNĐ vì không còn chỗ chứa trong kho đồ, số tiền còn lại đã được đưa lại vào ví");
						$money->addMoney($player, $amount);
						return;
					}
					$player->getInventory()->addItem($item);
					$amount -= 100;
				}
				while ($amount >= 50) {
					$item = ItemFactory::get(Item::GUNPOWDER, 0, 1);
					$item->setCustomName("50.000VNĐ");
						if (!$player->getInventory()->canAddItem($item)) {
						$player->sendMessage($this->tag . "Không thể rút thêm 50.000VNĐ vì không còn chỗ chứa trong kho đồ, số tiền còn lại đã được đưa lại vào ví");
						$money->addMoney($player, $amount);
						return;
					}
					$player->getInventory()->addItem($item);
					$amount -= 50;
				}
				while ($amount >= 20) {
					$item = ItemFactory::get(Item::GHAST_TEAR, 0, 1);
					$item->setCustomName("20.000VNĐ");
					if (!$player->getInventory()->canAddItem($item)) {
						$player->sendMessage($this->tag . "Không thể rút thêm 20.000VNĐ vì không còn chỗ chứa trong kho đồ, số tiền còn lại đã được đưa lại vào ví");
						$money->addMoney($player, $amount);
						return;
					}
					$player->getInventory()->addItem($item);
					$amount -= 20;
				}
				while ($amount >= 10) {
					$item = ItemFactory::get(Item::HEART_OF_THE_SEA, 0, 1);
					$item->setCustomName("10.000VNĐ");
					if (!$player->getInventory()->canAddItem($item)) {
						$player->sendMessage($this->tag . "Không thể rút thêm 10.000VNĐ vì không còn chỗ chứa trong kho đồ, số tiền còn lại đã được đưa lại vào ví");
						$money->addMoney($player, $amount);
						return;
					}
					$player->getInventory()->addItem($item);
					$amount -= 10;
				}
				while ($amount >= 5) {
					$item = ItemFactory::get(Item::NAUTILUS_SHELL, 0, 1);
					$item->setCustomName("5.000VNĐ");
					if (!$player->getInventory()->canAddItem($item)) {
						$player->sendMessage($this->tag . "Không thể rút thêm 5.000VNĐ vì không còn chỗ chứa trong kho đồ, số tiền còn lại đã được đưa lại vào ví");
						$money->addMoney($player, $amount);
						return;
					}
					$player->getInventory()->addItem($item);
					$amount -= 5;
				}
				while ($amount >= 2) {
					$item = ItemFactory::get(Item::FEATHER, 0, 1);
					$item->setCustomName("2.000VNĐ");
					if (!$player->getInventory()->canAddItem($item)) {
						$player->sendMessage($this->tag . "Không thể rút thêm 2.000VNĐ vì không còn chỗ chứa trong kho đồ, số tiền còn lại đã được đưa lại vào ví");
						$money->addMoney($player, $amount);
						return;
					}
					$player->getInventory()->addItem($item);
					$amount -= 2;
				}
				while ($amount >= 1) {
					$item = ItemFactory::get(Item::PRISMARINE_CRYSTALS, 0, 1);
					$item->setCustomName("1.000VNĐ");
					if (!$player->getInventory()->canAddItem($item)) {
						$player->sendMessage($this->tag . "Không thể rút thêm 1.000VNĐ vì không còn chỗ chứa trong kho đồ, số tiền còn lại đã được đưa lại vào ví");
						$money->addMoney($player, $amount);
						return;
					}
					$player->getInventory()->addItem($item);
					$amount -= 1;
				}
				if (strlen($data[0]) > 3) {
					$final = substr_replace($data[0], ".", strlen($data[0])-3, 0);
				}
				$player->sendMessage($this->tag . "Bạn đã rút " . $final . ".000VNĐ từ trong ví");
			}
		});
		$form->setTitle("Rút tiền");
		$form->addInput("Nhập số tiền cần rút (VD: 500 để rút 500.000VNĐ)");
		$form->sendToPlayer($player);
	}

	public function naptienF(Player $player) {
		$player->removeAllWindows();
		$formapi = Server::getInstance()->getPluginManager()->getPlugin("FormAPI");
		$form = $formapi->createCustomForm(function (Player $player, Array $data = null) {
			$money = EconomyAPI::getInstance();
			$default = $money->myMoney($player);
			$amount = (int)$data[0];

			if ($amount === null) {
				$player->sendMessage($this->tag . "Bạn phải nhập số tiền cần nạp!");
				return;
			}
			else {
				if (!is_numeric($amount)) {
					$player->sendMessage($this->tag . "Số tiền cần nạp phải là số!");
					return;
				}
			}

			$moneyy = [Item::PRISMARINE_CRYSTALS, Item::FEATHER, Item::NAUTILUS_SHELL, Item::HEART_OF_THE_SEA, Item::GHAST_TEAR, Item::GUNPOWDER, Item::LEATHER, Item::MAGMA_CREAM, Item::NETHER_WART];
			$hand = $player->getInventory()->getItemInHand();
			if (!in_array($hand->getId(), $moneyy)) {
				$player->sendMessage($this->tag . "Bạn phải cầm tiền trên tay!");
				return;
			}
			else {
				if ($hand->getId() === Item::NETHER_WART) {
					if ($hand->getCount() < $data[0]) {
						$final = 500 * $hand->getCount();
						$itemCount = $hand->getCount();
						$player->sendMessage($this->tag . "Bạn không có đủ " . $data[0] . " tờ trên người! Số tiền nạp vào sẽ là: " . $final . ".000VNĐ");
					}
					else {
						$final = 500 * $data[0];
						$itemCount = $data[0];
					}
					$player->getInventory()->removeItem(Item::get($hand->getId(), 0, $itemCount));
				}
				else if ($hand->getId() === Item::MAGMA_CREAM) {
					if ($hand->getCount() < $data[0]) {
						$final = 200 * $hand->getCount();
						$itemCount = $hand->getCount();
						$player->sendMessage($this->tag . "Bạn không có đủ " . $data[0] . " tờ trên người! Số tiền nạp vào sẽ là: " . $final . ".000VNĐ");
					}
					else {
						$final = 200 * $data[0];
						$itemCount = $data[0];
					}
					$player->getInventory()->removeItem(Item::get($hand->getId(), 0, $itemCount));
				}
				else if ($hand->getId() === Item::LEATHER) {
					if ($hand->getCount() < $data[0]) {
						$final = 100 * $hand->getCount();
						$itemCount = $hand->getCount();
						$player->sendMessage($this->tag . "Bạn không có đủ " . $data[0] . " tờ trên người! Số tiền nạp vào sẽ là: " . $final . ".000VNĐ");
					}
					else {
						$final = 100 * $data[0];
						$itemCount = $data[0];
					}
					$player->getInventory()->removeItem(Item::get($hand->getId(), 0, $itemCount));
				}
				else if ($hand->getId() === Item::GUNPOWDER) {
					if ($hand->getCount() < $data[0]) {
						$final = 50 * $hand->getCount();
						$itemCount = $hand->getCount();
						$player->sendMessage($this->tag . "Bạn không có đủ " . $data[0] . " tờ trên người! Số tiền nạp vào sẽ là: " . $final . ".000VNĐ");
					}
					else {
						$final = 50 * $data[0];
						$itemCount = $data[0];
					}
					$player->getInventory()->removeItem(Item::get($hand->getId(), 0, $itemCount));
				}
				else if ($hand->getId() === Item::GHAST_TEAR) {
					if ($hand->getCount() < $data[0]) {
						$final = 20 * $hand->getCount();
						$itemCount = $hand->getCount();
						$player->sendMessage($this->tag . "Bạn không có đủ " . $data[0] . " tờ trên người! Số tiền nạp vào sẽ là: " . $final . ".000VNĐ");
					}
					else {
						$final = 20 * $data[0];
						$itemCount = $data[0];
					}
					$player->getInventory()->removeItem(Item::get($hand->getId(), 0, $itemCount));
				}
				else if ($hand->getId() === Item::HEART_OF_THE_SEA) {
					if ($hand->getCount() < $data[0]) {
						$final = 10 * $hand->getCount();
						$itemCount = $hand->getCount();
						$player->sendMessage($this->tag . "Bạn không có đủ " . $data[0] . " tờ trên người! Số tiền nạp vào sẽ là: " . $final . ".000VNĐ");
					}
					else {
						$final = 10 * $data[0];
						$itemCount = $data[0];
					}
					$player->getInventory()->removeItem(Item::get($hand->getId(), 0, $itemCount));
				}
				else if ($hand->getId() === Item::NAUTILUS_SHELL) {
					if ($hand->getCount() < $data[0]) {
						$final = 5 * $hand->getCount();
						$itemCount = $hand->getCount();
						$player->sendMessage($this->tag . "Bạn không có đủ " . $data[0] . " tờ trên người! Số tiền nạp vào sẽ là: " . $final . ".000VNĐ");
					}
					else {
						$final = 5 * $data[0];
						$itemCount = $data[0];
					}
					$player->getInventory()->removeItem(Item::get($hand->getId(), 0, $itemCount));
				}
				else if ($hand->getId() === Item::FEATHER) {
					if ($hand->getCount() < $data[0]) {
						$final = 2 * $hand->getCount();
						$itemCount = $hand->getCount();
						$player->sendMessage($this->tag . "Bạn không có đủ " . $data[0] . " tờ trên người! Số tiền nạp vào sẽ là: " . $final . ".000VNĐ");
					}
					else {
						$final = 2 * $data[0];
						$itemCount = $data[0];
					}
					$player->getInventory()->removeItem(Item::get($hand->getId(), 0, $itemCount));
				}
				else if ($hand->getId() === Item::PRISMARINE_CRYSTALS) {
					if ($hand->getCount() < $data[0]) {
						$final = 1 * $hand->getCount();
						$itemCount = $hand->getCount();
						$player->sendMessage($this->tag . "Bạn không có đủ " . $data[0] . " tờ trên người! Số tiền nạp vào sẽ là: " . $final . ".000VNĐ");
					}
					else {
						$final = 1 * $data[0];
						$itemCount = $data[0];
					}
					$player->getInventory()->removeItem(Item::get($hand->getId(), 0, $itemCount));
				}
			}

			$money->addMoney($player, $final);
			$player->sendMessage($this->tag . "Bạn đã nạp " . $final . ".000VNĐ");
			
		});
		$form->setTitle("Nạp tiền");
		$form->addInput("Nhập số tờ trên tay cần nạp (VD: 2 để nạp 2 tờ)");
		$form->sendToPlayer($player);
	}

	public function chuyentienF(Player $player) {
		$player->removeAllWindows();
		$formapi = Server::getInstance()->getPluginManager()->getPlugin("FormAPI");
		$form = $formapi->createCustomForm(function (Player $player, array $data) {
			$money = EconomyAPI::getInstance();
			$target = Server::getInstance()->getPlayerExact($data[0]);
			$default = $money->myMoney($target);
			$default2 = $money->myMoney($player);
			if ($data[0] === null) {
				$player->sendMessage($this->tag . "Bạn phải nhập tên người nhận!");
				return;
			}
			else {
				if (!$target) {
					$player->sendMessage($this->tag . "Người chơi " . TextFormat::RED . $data[0] . TextFormat::WHITE . " không online!");
					return;
				}
			}

			if ($data[1] === null) {
				$player->sendMessage($this->tag . "Bạn phải nhập số tiền cần chuyển!");
				return;
			}
			else {
				if (!is_numeric($data[1])) {
					$player->sendMessage($this->tag . "Số tiền cần chuyển phải là số!");
					return;
				}
			}
			
			if ($data[1] > $money->myMoney($player)) {
				$player->sendMessage($this->tag . "Số tiền cần chuyển lớn hơn số dư trong tài khoản!");
				return;
			}

			$money->addMoney($target, $data[1]);
			$money->reduceMoney($player, $data[1]);

			if ($default < $money->myMoney($target) && $default2 > $money->myMoney($player)) {
				$player->sendMessage($this->tag . "Bạn đã gửi " . $data[1] . ".000VNĐ cho " . TextFormat::RED . $target->getName());
				$target->sendMessage($this->tag . "Bạn nhận " . $data[1] . ".000VNĐ từ " . TextFormat::RED . $player->getName());
				
				$level = $target->getLevel();
				$task = new dropTask($this->plugin, $level, $target);
				$this->plugin->getScheduler()->scheduleRepeatingTask($task, 5);
			}
		});
		
		$form->setTitle("Chuyển tiền");
		$form->addInput("Nhập tên người nhận");
		$form->addInput("Nhập số tiền cần chuyển");
		$form->sendToPlayer($player);
	}

	public function sendTo(Player $player) : void {
		$player->removeAllWindows();
		$this->nganhangM->send($player);
	}
}

class Main extends PluginBase implements Listener {
	
	public function onEnable(): void {
		if(!InvMenuHandler::isRegistered()){
			InvMenuHandler::register($this);
		}
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getLogger()->info(TextFormat::GOLD . "Priting text...
            __          __   _ _      _   
            \ \        / /  | | |    | |  
             \ \  /\  / /_ _| | | ___| |_ 
              \ \/  \/ / _` | | |/ _ \ __|
               \  /\  / (_| | | |  __/ |_ 
                \/  \/ \__,_|_|_|\___|\__|
                  SpermLord/DevNTNghia
		");
	}

	public function onTap(PlayerInteractEvent $event) {
		$player = $event->getPlayer();
		$itemID = $player->getInventory()->getItemInHand()->getId();
		$itemName = $player->getInventory()->getItemInHand()->getName();
		if ($itemID === Item::IRON_NUGGET && $itemName == "Thẻ ngân hàng") {
			$nganhang = new NganHang($player, $this);
			$nganhang->sendTo($player);
		}
	}

	public function PickUp(InventoryPickupItemEvent $e) {
		$itemEntity = $e->getItem();
		$item = $itemEntity->getItem();
		if($item->getName() === "SPERMLORD"){
			$e->setCancelled();
			$itemEntity->kill();
		}
	}


	public function onCraftEvent(CraftItemEvent $event) {
		$player = $event->getPlayer();
		foreach($event->getRecipe()->getResults() as $result) {
			if($result->getId() === Item::IRON_NUGGET) {
				$player->sendMessage(TextFormat::GOLD . "[" . TextFormat::GREEN . "Wallet" . TextFormat::GOLD . "] " . TextFormat::WHITE . "Bạn không thể chế tạo vật phẩm này!");
				$event->setCancelled();
				break;
			}
		}
	}
}