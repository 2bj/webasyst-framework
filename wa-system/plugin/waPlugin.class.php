<?php

class waPlugin
{
    protected $id;
    protected $app_id;
    protected $info = array();
    protected $path;

    public function __construct($info)
    {
        $this->info = $info;
        $this->id = $this->info['id'];
        if (isset($this->info['app_id'])) {
            $this->app_id = $this->info['app_id'];
        } else {
            $this->app_id = waSystem::getInstance()->getApp();
        }
        $this->path = wa()->getAppPath('plugins/'.$this->id, $this->app_id);

        $this->checkUpdates();
    }

    public function getName()
    {
        return $this->info['name'];
    }
    
    protected function checkUpdates()
    {
        $app_settings_model = new waAppSettingsModel();
        $time = $app_settings_model->get(array($this->app_id, $this->id), 'update_time');
        if (!$time) {
            try {
                $this->install();
                $app_settings_model->set(array($this->app_id, $this->id), 'update_time', 1);
            } catch (Exception $e) {
                waLog::log($e->getMessage());
                return;
            }
        }
    }
    
    protected function install()
    {
        // check plugin.sql
        $file_sql = $this->path.'/lib/config/plugin.sql';
        if (file_exists($file_sql)) {
            waAppConfig::executeSQL($file_sql, 1);
        }
        // check install.php
        $file = $this->path.'/lib/config/install.php';
        if (file_exists($file)) {
            $app_id = $this->app_id;
            include($file);
        }
    }
    
    public function uninstall()
    {
        // check uninstall.php
        $file = $this->path.'/lib/config/uninstall.php';
        if (file_exists($file)) {
            include($file);
        }
        // check plugin.sql
        $file_sql = $this->path.'/lib/config/plugin.sql';
        if (file_exists($file_sql)) {
            waAppConfig::executeSQL($file_sql, 2);
        }
        // Remove plugin settings
        $app_settings_model = new waAppSettingsModel();
        $sql = "DELETE FROM ".$app_settings_model->getTableName()."
                WHERE app_id = s:app_id";
        $app_settings_model->exec($sql, array('app_id' => $this->app_id.".".$this->id));

        if (!empty($this->info['rights'])) {
            // Remove rights to plugin
            $contact_rights_model = new waContactRightsModel();
            $sql = "DELETE FROM ".$contact_rights_model->getTableName()."
                    WHERE app_id = s:app_id AND (
                        name = '".$contact_rights_model->escape('plugin.'.$this->id)."' OR
                        name LIKE '".$contact_rights_model->escape('plugin.'.$this->id).".%'
                    )";
            $contact_rights_model->exec($sql, array('app_id' => $this->app_id));
        }

        // Remove cache of the appliaction
        waFiles::delete(wa()->getAppCachePath('', $this->app_id));
    }
    

    public function getPluginStaticUrl() 
    {
        return wa()->getAppStaticUrl($this->app_id).'plugins/'.$this->id.'/';
    }

    public function getRights($name = '', $assoc = true) 
    {
        $right = 'plugin.'.$this->id;
        if ($name) {
            $right .= '.'.$name;
        }
        return wa()->getUser()->getRights(wa()->getConfig()->getApplication(), $right, $assoc);
    }
    
    public function rightsConfig(waRightConfig $rights_config)
    {
        $rights_config->addItem('plugin.'.$this->id, $this->info['name'], 'checkbox');
    }
    
    protected function getUrl($url, $is_plugin)
    {
        if ($is_plugin) {
            return 'plugins/'.$this->id.'/'.$url;
        } else {
            return $url;
        }
    }
    
    protected function addJs($url, $is_plugin = true)
    {
        waSystem::getInstance()->getResponse()->addJs($this->getUrl($url, $is_plugin),$this->app_id);
    }
    
    protected function addCss($url, $is_plugin = true)
    {
        waSystem::getInstance()->getResponse()->addCss($this->getUrl($url, $is_plugin),$this->app_id);
    }

    public function routing()
    {
        $file = $this->path.'/lib/config/routing.php';
        if (file_exists($file)) {
            return include($file);
        } else {
            return array();
        }
    }
}

