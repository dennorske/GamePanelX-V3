<?php
define('DOCROOT', '../');
require(DOCROOT.'/lang.php');
require('version.php');
session_start();

// Check install
if(!file_exists(DOCROOT.'/configuration.php')) die('No /configuration.php file found!  Check your installation before trying to update.');

require(DOCROOT.'/configuration.php');

// Setup db
@mysql_connect($settings['db_host'], $settings['db_username'], $settings['db_password']) or die('Failed to connect to the database!  Check your settings and try again.');
@mysql_select_db($settings['db_name']) or die('Failed to select the database!  Check your settings and try again.');

// Get current version
$result_cfg   = @mysql_query("SELECT config_value FROM configuration WHERE config_setting = 'version' ORDER BY last_updated DESC LIMIT 1");
$row_cfg      = mysql_fetch_row($result_cfg);
$cur_version  = $row_cfg[0];

// Function to update version number to new version
function update_gpxver($this_ver)
{
    $new_version  = GPX_VERSION;
    
    if(empty($new_version)) die('No new version found!  Check your "install/version.php" file.');
    @mysql_query("UPDATE configuration SET config_value = '$new_version' WHERE config_setting = 'version'") or die('Failed to update version: '.mysql_error());
    
    // Set new version to current
    if($this_ver) $cur_version  = $this_ver;
}

// No version? Start with 3.0.3 (no version was in DB prior to 3.0.5)
if(empty($cur_version))
{
    @mysql_query("INSERT INTO configuration (config_setting,config_value) VALUES('version','3.0.3')");
    $cur_version = '3.0.3';
}

// Already up to date
if(GPX_VERSION == $cur_version) die('You are already up to date (v'.$cur_version.')! <a href="../admin/">Back to Admin Area</a>');

################################################################################################################################################

//
// Incremental Updates - Update database from any previous version all the way to the latest
//


// 3.0.4
if($cur_version < '3.0.4')
{
    // Add `banned_chars` to default_games
    @mysql_query("ALTER TABLE default_games ADD `banned_chars` VARCHAR(64) NOT NULL AFTER pid_file") or die('Failed to add banned_chars: '.mysql_error());
    
    // Add default banned chars to cs series games
    @mysql_query("UPDATE default_games SET banned_chars = '+- ' WHERE intname IN('cs_16','cs_cz','cs_s','cs_go')") or die('Failed to update banned chars: '.mysql_error());
    
    update_gpxver('3.0.4');
}

// 3.0.5
if($cur_version < '3.0.5')
{
    update_gpxver('3.0.5');
}

// 3.0.6
if($cur_version < '3.0.6')
{
    // Minecraft updates
    @mysql_query("UPDATE default_games SET gameq_name = 'minecraft',cloudid = '6',simplecmd = 'java -Xincgc -Xmx1000M -jar craftbukkit.jar nogui' WHERE intname = 'mcraft'") or die('Failed to update Minecraft support: '.mysql_error());
    
    // Counter-Strike updates
    @mysql_query("UPDATE default_games SET update_cmd = './steam -command update -game cstrike -dir .' WHERE intname = 'cs_16'") or die('Failed to update CS 1.6 support: '.mysql_error());
    @mysql_query("UPDATE default_games SET update_cmd = './steam -command update -game czero -dir .' WHERE intname = 'cs_cz'") or die('Failed to update CS CZ support: '.mysql_error());
    @mysql_query("UPDATE default_games SET update_cmd = './steam -command update -game \'Counter-Strike Source\' -dir .' WHERE intname = 'cs_s'") or die('Failed to update CS S support: '.mysql_error());
    @mysql_query("UPDATE default_games SET cloudid = '7' WHERE intname = 'cs_go'") or die('Failed to update CS GO support: '.mysql_error());
    
    // Add language support
    @mysql_query("ALTER TABLE admins ADD `language` VARCHAR(64) NOT NULL DEFAULT 'english' AFTER `password`") or die('Failed to add admin language: '.mysql_error());
    @mysql_query("ALTER TABLE users ADD `language` VARCHAR(64) NOT NULL DEFAULT 'english' AFTER `last_updated`") or die('Failed to add user language: '.mysql_error());
    
    update_gpxver('3.0.6');
}

// 3.0.8
if($cur_version < '3.0.8')
{
    // Add theme support
    @mysql_query("ALTER TABLE admins ADD `theme` VARCHAR(64) NOT NULL DEFAULT 'default' AFTER `password`") or die('Failed to add admin theme: '.mysql_error());
    @mysql_query("ALTER TABLE users ADD `theme` VARCHAR(64) NOT NULL DEFAULT 'default' AFTER `last_updated`") or die('Failed to add user theme: '.mysql_error());
    
    // Add new columns to `default_games`
    @mysql_query("ALTER TABLE `default_games` 
                    ADD `maxplayers` SMALLINT(4) UNSIGNED NOT NULL AFTER `port`,
                    ADD `cfg_separator` VARCHAR(1) NOT NULL AFTER `steam`,
                    ADD `install_mirrors` VARCHAR(600) NOT NULL AFTER `description`,
                    ADD `install_cmd` VARCHAR(600) NOT NULL AFTER `install_mirrors`,
                    ADD `cfg_ip` VARCHAR(64) NOT NULL AFTER `banned_chars`,
                    ADD `cfg_port` VARCHAR(64) NOT NULL AFTER `cfg_ip`,
                    ADD `cfg_maxplayers` VARCHAR(64) NOT NULL AFTER `cfg_port`,
                    ADD `cfg_map` VARCHAR(64) NOT NULL AFTER `cfg_maxplayers`,
                    ADD `cfg_hostname` VARCHAR(64) NOT NULL AFTER `cfg_map`,
                    ADD `cfg_rcon` VARCHAR(64) NOT NULL AFTER `cfg_hostname`,
                    ADD `cfg_password` VARCHAR(64) NOT NULL AFTER `cfg_rcon`,
                    ADD `map` VARCHAR(255) NOT NULL AFTER `cfg_password`,
                    ADD `hostname` VARCHAR(255) NOT NULL AFTER `map`,
                    ADD `config_file` VARCHAR(255) NOT NULL AFTER `cfg_password`") or die('Failed to add default_games columns: '.mysql_error());
    
    // Add new columns to `servers`
    @mysql_query("ALTER TABLE `servers` 
                    ADD `maxplayers` SMALLINT(4) UNSIGNED NOT NULL AFTER `port`,
                    ADD `map` VARCHAR(255) NOT NULL AFTER `token`,
                    ADD `rcon` VARCHAR(255) NOT NULL AFTER `map`,
                    ADD `hostname` VARCHAR(255) NOT NULL AFTER `rcon`,
                    ADD `sv_password` VARCHAR(255) NOT NULL AFTER `hostname`") or die('Failed to add default_games columns: '.mysql_error());
    
    // Add local config paths for all games (including working dir)
    @mysql_query("UPDATE `default_games` SET `config_file` = CASE `intname` 
                      WHEN 'cs_16' THEN 'cstrike/cfg/server.cfg'
                      WHEN 'cs_cz' THEN 'cstrike/cfg/server.cfg'
                      WHEN 'cs_s' THEN 'cstrike/cfg/server.cfg'
                      WHEN 'cs_go' THEN 'cfg/server.cfg'
                      WHEN 'mcraft' THEN 'Server.Properties'
                      WHEN 'gta_samp' THEN 'server.cfg'
                      WHEN 'bf2' THEN 'mods/bf2/settings/serversettings.con'
                  ELSE `config_file` END") or die('Failed to update default games: '.mysql_error());
    
    ########
    
    // Update all cfg_* values
    @mysql_query("UPDATE `default_games` SET `cfg_separator` = CASE `intname` 
                      WHEN 'cs_16' THEN ' '
                      WHEN 'cs_cz' THEN ' '
                      WHEN 'cs_s' THEN ' '
                      WHEN 'cs_go' THEN ' '
                      WHEN 'mcraft' THEN '='
                      WHEN 'samp' THEN ' '
                      WHEN 'bf2' THEN ' '
                      WHEN 'vent' THEN '='
                  ELSE `cfg_separator` END") or die('Failed to update separators: '.mysql_error());
    
    @mysql_query("UPDATE `default_games` SET `cfg_ip` = CASE `intname` 
                      WHEN 'cs_16' THEN 'ip'
                      WHEN 'cs_cz' THEN 'ip'
                      WHEN 'cs_s' THEN 'ip'
                      WHEN 'cs_go' THEN 'ip'
                      WHEN 'mcraft' THEN 'server-ip'
                      WHEN 'bf2' THEN 'sv.serverIP'
                  ELSE `cfg_ip` END") or die('Failed to update ips: '.mysql_error());
    
    @mysql_query("UPDATE `default_games` SET `cfg_port` = CASE `intname` 
                      WHEN 'cs_16' THEN 'port'
                      WHEN 'cs_cz' THEN 'port'
                      WHEN 'cs_s' THEN 'port'
                      WHEN 'cs_go' THEN 'port'
                      WHEN 'mcraft' THEN 'server-port'
                      WHEN 'gta_samp' THEN 'port'
                      WHEN 'bf2' THEN 'sv.serverPort'
                  ELSE `cfg_port` END") or die('Failed to update ports: '.mysql_error());
    
    @mysql_query("UPDATE `default_games` SET `cfg_maxplayers` = CASE `intname` 
                      WHEN 'cs_16' THEN 'maxplayers'
                      WHEN 'cs_cz' THEN 'maxplayers'
                      WHEN 'cs_s' THEN 'maxplayers'
                      WHEN 'cs_go' THEN 'maxplayers_override'
                      WHEN 'mcraft' THEN 'max-players'
                      WHEN 'gta_samp' THEN 'maxplayers'
                      WHEN 'bf2' THEN 'sv.maxPlayers'
                      WHEN 'vent' THEN 'MaxClients'
                  ELSE `cfg_maxplayers` END") or die('Failed to update maxplayers: '.mysql_error());
    
    @mysql_query("UPDATE `default_games` SET `cfg_map` = CASE `intname` 
                      WHEN 'cs_16' THEN 'map'
                      WHEN 'cs_cz' THEN 'map'
                      WHEN 'cs_s' THEN 'map'
                      WHEN 'cs_go' THEN 'map'
                      WHEN 'mcraft' THEN 'level-name'
                      WHEN 'gta_samp' THEN 'mapname'
                  ELSE `cfg_map` END") or die('Failed to update map: '.mysql_error());
    
    @mysql_query("UPDATE `default_games` SET `cfg_hostname` = CASE `intname` 
                      WHEN 'cs_16' THEN 'hostname'
                      WHEN 'cs_cz' THEN 'hostname'
                      WHEN 'cs_s' THEN 'hostname'
                      WHEN 'cs_go' THEN 'hostname'
                      WHEN 'mcraft' THEN 'motd'
                      WHEN 'gta_samp' THEN 'hostname'
                      WHEN 'bf2' THEN 'sv.serverName'
                      WHEN 'vent' THEN 'Name'
                  ELSE `cfg_hostname` END") or die('Failed to update hostname: '.mysql_error());
    
    @mysql_query("UPDATE `default_games` SET `cfg_rcon` = CASE `intname` 
                      WHEN 'cs_16' THEN 'rcon_password'
                      WHEN 'cs_cz' THEN 'rcon_password'
                      WHEN 'cs_s' THEN 'rcon_password'
                      WHEN 'cs_go' THEN 'rcon_password'
                      WHEN 'mcraft' THEN 'rcon.password'
                      WHEN 'gta_samp' THEN 'rcon_password'
                      WHEN 'vent' THEN 'AdminPassword'
                  ELSE `cfg_rcon` END") or die('Failed to update rcon: '.mysql_error());
    
    @mysql_query("UPDATE `default_games` SET `cfg_password` = CASE `intname` 
                      WHEN 'cs_16' THEN 'sv_password'
                      WHEN 'cs_cz' THEN 'sv_password'
                      WHEN 'cs_s' THEN 'sv_password'
                      WHEN 'cs_go' THEN 'sv_password'
                      WHEN 'mcraft' THEN 'rcon.password'
                      WHEN 'gta_samp' THEN 'rcon_password'
                      WHEN 'vent' THEN 'AdminPassword'
                  ELSE `cfg_password` END") or die('Failed to update rcon: '.mysql_error());
    
    ########
    
    // Update install config for minecraft
    @mysql_query("UPDATE `default_games` SET port = '25565',install_mirrors = 'http://dl.bukkit.org/latest-rb/craftbukkit.jar',install_cmd = 'mv craftbukkit* craftbukkit.jar' WHERE intname = 'mcraft'") or die('Failed to update minecraft: '.mysql_error());
    
    // Update CS:GO steam name from "csgo" to "740" to use steamcmd app ID
    @mysql_query("UPDATE `default_games` SET steam = '2',steam_name = '740' WHERE intname = 'cs_go'") or die('Failed to update csgo: '.mysql_error());
    
    // Update `startup` to 0 for non-startup games
    @mysql_query("UPDATE `default_games` SET `startup` = '0' WHERE intname IN ('vent','bf2','mcraft')") or die('Failed to update startup 0: '.mysql_error());
    
    // Add steam config items
    @mysql_query("INSERT INTO configuration (config_setting,config_value) VALUES('steam_login_user',''),('steam_login_pass',''),('steam_auth','')") or die('Failed to update configuration: '.mysql_error());
    
    
    
    // Add SA:MP support
    @mysql_query("INSERT INTO `default_games` (`id`, `cloudid`, `port`, `startup`, `steam`, `gameq_name`, `name`, `intname`, `working_dir`, `pid_file`, `banned_chars`, `steam_name`, `description`, `install_mirrors`, `install_cmd`, `update_cmd`, `simplecmd`) VALUES('', 9, 7777, 0, 0, 'mtasa', 'GTA: San Andreas MP', 'gta_samp', '', '', '', '', 'Grand Theft Auto: San Andreas - Multiplayer', 'http://files.sa-mp.com/samp03asvr_R4.tar.gz', 'tar -zxvf files.sa-mp.com/samp03asvr_R4.tar.gz; mv samp03/* .; rm -fr samp03 samp03asvr_R4.tar.gz', '', './samp03svr')");
    
    
    update_gpxver('3.0.8');
}



// Completed
echo '<b>Success!</b> Update completed successfully.  Now delete or rename your "/install" directory, then <a href="../admin/">back to Admin Area</a>';

?>