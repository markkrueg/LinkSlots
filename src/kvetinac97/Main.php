<?php

namespace kvetinac97;

use pocketmine\event\Listener;
use pocketmine\event\server\QueryRegenerateEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener {

    /** @var Config $config */
    public $config;

    /** @var int $players */
    public $players = 0;
    /** @var int $maxPlayers */
    public $maxPlayers = 0;

    public function onEnable() {
        $this->getLogger()->info("§bLinkSlots §aENABLED!");
        $this->getLogger()->info("§7Running version §e1.1.0...");
        $this->saveDefaultConfig();
        $this->config = new Config($this->getDataFolder()."config.yml", Config::YAML);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->setPlayers();
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new RefreshTask($this), $this->config->get("interval")*60*20);
    }

    public function onDisable() {
        $this->getLogger()->info("§bLinkSlots §cDISABLED!");
    }


    public function setPlayers($task = false){
        $this->players = 0;
        $this->maxPlayers = 0;
        if (!$task && ($this->config->get("servers") === [] || !$this->config->get("servers"))){
            $this->getLogger()->critical("§4Could not load plugin: you haven't set any servers to config.yml!");
            $this->getLogger()->warning("§cDisabling §bLinkSlots...");
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }

        foreach ($this->config->get("servers") as $port => $ip){

			#The following code was taken from DServerTask.php at Genisys project https://github.com/iTXTech/Genisys
			#Author MUedsa, PeratX
			#Licensed GNU GPL v3 as posted https://github.com/iTXTech/Genisys/blob/master/LICENSE
			$client = stream_socket_client("udp://" . $ip . ":" . $port, $errno, $errstr);    //非阻塞Socket
			if($client){
				stream_set_timeout($client, 1);
				$Handshake_to = "\xFE\xFD" . chr(9) . pack("N", 233);
				fwrite($client, $Handshake_to);
				$Handshake_re_1 = fread($client, 65535);
				if($Handshake_re_1 != ""){
					$Handshake_re = $this->decode($Handshake_re_1);
					$Status_to = "\xFE\xFD" . chr(0) . pack("N", 233) . pack("N", $Handshake_re["payload"]);
					fwrite($client, $Status_to);
					$Status_re_1 = fread($client, 65535);
					if($Status_re_1 != ""){
						$Status_re = $this->decode($Status_re_1);
						$ServerData = explode("\x00", $Status_re["payload"]);
						$onl = $ServerData[3];
						$max = $ServerData[4];
					}
				}
			fclose($client);
			#END Genisys DServerTask.php code https://github.com/iTXTech/Genisys
		}
			
			#Debug code. Un-comment to show each server connection and results.
			#$this->getLogger()->info("Server Checked ".$ip.":".$port." Online: ".$onl." Max: ".$max);

            if ($onl == null || $max == null){
                if (!$task){
                    $this->getLogger()->warning("§cCould not connect to §e$ip:§b$port; §cThe server is offline");
                }
                continue;
            }
            $this->players += $onl;
            $this->maxPlayers += $max;
        }
        if ($this->config->get("add_self_slots") == "true"){
            $this->players += \count($this->getServer()->getOnlinePlayers());
            $this->maxPlayers += $this->getServer()->getMaxPlayers();
        }
	}
	
    public function onQuery(QueryRegenerateEvent $e){
        $e->setPlayerCount($this->players);
        $e->setMaxPlayerCount($this->maxPlayers);
    }

	#The following code was taken from DServerTask.php at Genisys project https://github.com/iTXTech/Genisys
	#Author MUedsa, PeratX
	#Licensed GNU GPL v3 as posted https://github.com/iTXTech/Genisys/blob/master/LICENSE
    public function decode($buffer){
		$redata = [];
		$redata["packetType"] = ord($buffer{0});
		$redata["sessionID"] = unpack("N", substr($buffer, 1, 4))[1];
		$redata["payload"] = rtrim(substr($buffer, 5));
		return $redata;
	}
	#END Genisys DServerTask.php code https://github.com/iTXTech/Genisys

}

class RefreshTask extends Task{

    public $plugin;

    public function __construct(Main $plugin){
        $this->plugin = $plugin;
    }

    public function onRun($currentTick) {
        $this->plugin->setPlayers(true);
    }
}