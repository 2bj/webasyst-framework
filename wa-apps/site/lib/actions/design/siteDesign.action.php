<?php 

class siteDesignAction extends waViewAction
{
    public function execute()
    {
        $route_id = waRequest::get('route');
        $domain = siteHelper::getDomain();

        $routes = wa()->getRouting()->getRoutes($domain);
        if (!isset($routes[$route_id])) {
            throw new waException(_w("Route not found"), 404);
        }
        $route = $routes[$route_id];
        $app_id = $route['app'];
        $app = wa()->getAppInfo($app_id);
        $this->setLayout(new siteDesignLayout());
        $theme_id = null;

        if (!empty($app['themes'])) {
            $theme_id = waRequest::get('theme');
            if (!$theme_id) {
                $themes = siteHelper::getThemes($app_id, false);
                $themes = siteHelper::sortThemes($themes, $route);
                $theme_id = current(array_keys($themes));
            }
            $theme = new waTheme($theme_id, $app_id);
            if (($f = waRequest::get('file')) !== '') {
                if (!$f) {
                    $files = $theme['files'];
                    if (isset($files['index.html'])) {
                        $f = 'index.html';
                    } else {
                        ksort($files);
                        $files = array_keys($files);
                        $f = current($files);
                    }
                }
                $file = $theme->getFile($f);
                $file['id'] = $f;
                if (!empty($theme['parent_theme_id']) && $file['parent']) {
                    if (!waTheme::exists($theme['parent_theme_id'], $app_id)) {
                        if (strpos($theme['parent_theme_id'], ':') !== false) {
                            list($app_id, $theme_id) = explode(':', $theme['parent_theme_id'], 2);
                            $app = wa()->getAppInfo($app_id);
                        } else {
                            $theme_id = $theme['parent_theme_id'];
                        }
                        $this->view->assign('error', sprintf(_ws('Theme %s for “%s” app not found.'), $theme_id, $app['name']));
                        $this->template = 'DesignError';
                        return;
                    }
                    $parent_theme = new waTheme($theme['parent_theme_id'], $app_id);
                    $path = $parent_theme->getPath();
                } else {
                    $path = $theme->getPath();
                }
                $path .= '/'.$f;
                $content = file_exists($path) ? file_get_contents($path) : '';
                $this->view->assign('content', $content);
            } else {
                $file = array(
                    'id' => null,
                    'description' => '',
                    'custom' => true
                );
            }
        } else {
            $file = null;
            $this->template = 'DesignNo';
        }

        $this->view->assign('file', $file);
        $this->view->assign('theme', $theme_id);
        $this->view->assign('route_id', $route_id);
        $this->view->assign('route', $route);

        $this->view->assign('app_id', $app_id);
        $this->view->assign('app', $app);

        $this->view->assign('domain_id', siteHelper::getDomainId());
        $this->view->assign('domain', $domain);
    }
}