<?php

class waAppViewHelper
{
    /**
     * @var waSystem
     */
    protected $wa;

    public function __construct($system)
    {
        $this->wa = $system;
    }

    public function pages($with_params = true)
    {
        try {
            $page_model = $this->getPageModel();
            $exclude_ids = waRequest::param('_exclude');
            $sql = "SELECT id, name, title, url, create_datetime, update_datetime FROM ".$page_model->getTableName().'
                    WHERE status = 1'.
                    ($exclude_ids ? " AND id NOT IN (:ids)" : '').
                    ' ORDER BY sort';
            $pages = $page_model->query($sql, array('ids' => $exclude_ids))->fetchAll('id');

            if ($with_params) {
                $page_params_model = $page_model->getParamsModel();
                $data = $page_params_model->getByField('page_id', array_keys($pages), true);
                foreach ($data as $row) {
                    if (isset($pages[$row['page_id']])) {
                        $pages[$row['page_id']][$row['name']] = $row['value'];
                    }
                }
            }
            // get current rool url
            $url = $this->wa->getAppUrl(null, true);

            foreach ($pages as &$page) {
                $page['url'] = $url.$page['url'];
                if (!isset($page['title']) || !$page['title']) {
                    $page['title'] = $page['name'];
                }
                foreach ($page as $k => $v) {
                    if ($k != 'content') {
                        $page[$k] = htmlspecialchars($v);
                    }
                }
            }
            return $pages;
        } catch (Exception $e) {
            return array();
        }
    }

    public function page($id)
    {
        $page_model = $this->getPageModel();
        $page = $page_model->getById($id);
        $page['content'] = $this->wa->getView()->fetch('string:'.$page['content']);

        $page_params_model = new sitePageParamsModel();
        $page += $page_params_model->getById($id);

        return $page;
    }


    /**
     * @return waPageModel
     */
    protected function getPageModel()
    {
        $class = $this->wa->getApp().'PageModel';
        return new $class();
    }

}