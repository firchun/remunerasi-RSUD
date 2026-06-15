<?php
class BaseController
{
    protected $db;
    protected $pageTitle;
    protected $extraHead;
    protected $extraFooter;
    protected $rootPath;

    public function __construct()
    {
        try {
            $this->db = Database::getInstance();
        } catch (Exception $e) {
            die('Database error: ' . $e->getMessage());
        }
    }

    protected function setTitle($title)
    {
        $this->pageTitle = $title;
    }

    protected function render($viewPath, $data = [])
    {
        extract($data);
        $pageTitle = $this->pageTitle;
        $extraHead = $this->extraHead ?? '';
        $extraFooter = $this->extraFooter ?? '';
        $rootPath = $this->rootPath ?? '';

        require_once VIEWS_PATH . '/layouts/header.php';
        require_once VIEWS_PATH . '/' . $viewPath;
        require_once VIEWS_PATH . '/layouts/footer.php';
        exit;
    }

    protected function renderRaw($viewPath, $data = [])
    {
        extract($data);
        require_once VIEWS_PATH . '/' . $viewPath . '.php';
        exit;
    }

    protected function redirect($url)
    {
        header('Location: ' . $url);
        exit;
    }
}
