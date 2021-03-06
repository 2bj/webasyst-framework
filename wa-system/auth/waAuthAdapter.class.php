<?php 

abstract class waAuthAdapter
{
    
    protected $options = array();
    
    public function __construct($options = array())
    {
        if (is_array($options)) {
            foreach ($options as $k => $v) {
                $this->options[$k] = $v;
            }
        }
    }

    public function getOption($name, $default = null)
    {
        return isset($this->options[$name]) ? $this->options[$name] : $default;
    }

    abstract public function auth();

    public function getId()
    {
        $class = get_class($this);
        return substr($class, 0, -4);
    }

    public function getName()
    {
        $class = get_class($this);
        return ucfirst(substr($class, 0, -4));
    }

    public function getIcon()
    {
        return wa()->getRootUrl().'wa-content/img/auth/'.$this->getId().'.png';
    }

    public function getCallbackUrl($absolute = true)
    {
        return wa()->getRootUrl($absolute).'oauth.php?provider='.$this->getId();
    }

    protected function get($url)
    {
        return file_get_contents($url);
    }

    protected function post($url, $post_data)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

        $content = curl_exec($ch);
        curl_close($ch);

        return $content;
    }
}