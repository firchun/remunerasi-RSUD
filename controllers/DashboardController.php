<?php
class DashboardController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Dashboard - RSUD MERAUKE';
        $this->rootPath = '';
    }

    public function index()
    {
        $this->render('dashboard.php');
    }
}
