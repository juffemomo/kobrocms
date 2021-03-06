<?php
/**
 * This be the main KobroCRM klass.
 * 
 * @author Devadutt Chattopadhyay
 * @author Rajanigandha Balasubramanium
 * @author Lalitchandra Pakalomattam
 *   
 */
class KobroCms
{
	/**
	 * Config ini be parsed to array here.
	 * 
	 * @var array
	 */
	public $config;
	
	/**
	 * This be PDO reference
	 * 
	 * @var PDO 
	 */
	public $db;
	
	/**
	 * Page
	 * 
	 * @var stdClass
	 */
	public $page;
	
	/**
	 * View
	 * 
	 * @var View
	 */
	public $view;
		
	
	/**
	 * User
	 * 
	 * @var User
	 */
	public $user;
        
        /*
         * Validaattori
         * 
         */
        public $validator;
        
        public $escaper;
        
	
	private function __construct()
	{
		// We parse customers config.
		$this->config = $config = parse_ini_file(ROOT . "/config.ini");
				
		// We connect to database
		$this->db = new PDO("mysql:host={$this->config['db_host']};dbname={$this->config['db_schema']}", $this->config['db_user'], $this->config['db_password']);
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                  
                $this->validator = new Validator();
                $this->escaper = new Escaper();
        }
	
	
	
	/**
	 * Return instance of CMS
	 * 
	 * @return KobroCms
	 */
	public static function getInstance()
	{
		static $instance;
		if(!$instance) {
			$instance = new KobroCms();
		}
		
		return $instance;
	}
		
	
	/**
	 * Returns page
	 * 
	 * @param $pageId Page id
	 * @return stdClass
	 */
	public function getPage($pageId)
	{
            // We be casting dem page id to integer so the parameter always valid.
            $pageId = (int) $pageId;
            try
               {
                 $this->validator->validateId($pageId);
               }
               catch(Exception $e)
               {                     
                 $message = "Method: ".__METHOD__." ".$e->getMessage()."\n";
                 file_put_contents(ROOT.'/logs/ValidationErrors', $message, FILE_APPEND);   
                 die();
               }
            try
             {

                 $query = "SELECT * FROM page WHERE id = ?";
                 $statement = $this->db->prepare($query);
                 $parameters = array($pageId);
                 if($statement->execute($parameters))
                 {
                   return $statement->fetch(PDO::FETCH_OBJ);
                 } 
             }
            catch(PDOException $e)
            {
                file_put_contents(ROOT.'/logs/PDOErrors', $e->getMessage(), FILE_APPEND); 
                echo $e->getMessage();
                die();
            } 				
	}
	
	
	/**
	 * Executing a module command
	 * 
	 * @param $params array
	 * @return string Module return html always
	 */
	public function executeModule($params)
	{
		// Autoload module from modules directory intelligently.
		$module = 'Module_' . $params['module'];
		require_once ROOT . '/modules/' . $params['module'] . '.php';
		$module = new $module();
		
		// Delegating executable
		return $module->execute($params);
		
	}
	
	
	
	/**
	 * Main runner kobros
	 * 
	 * @return string Html response to give user
	 */
	public function run()
	{
                if(isset($_REQUEST['s']))
                {
                    $_REQUEST['s'] = $this->escaper->escapeHtml($_REQUEST['s']);

                }

		// Init user
		$this->user = User::getInstance();

		// Init view
		$this->view = new View();
		
		// Fetch page. If no page use start page.
		$pageId = (isset($_GET['page'])) ? $_GET['page'] : $this->config['page_default']; 
		$this->page = $page = $this->getPage($pageId);
		
		// If invalid page we throw exception
		if(!$this->page) {
			throw new Exception('Page not found');
		}
		
		// Render inner-template
 
		$this->view->innertpl = $this->view->render(ROOT . '/templates/inner/' . $page->innertpl . '.phtml');
		
		// If user request template we use it
		$tpl = (isset($_REQUEST['tpl'])) ? $_REQUEST['tpl'] : $page->tpl; 
                //$message = "$tpl\n";
                //file_put_contents(ROOT.'/logs/generalDebug', $message, FILE_APPEND);  
		
		// HTML TITLE is always page titel.
		$this->view->title = $this->page->title;
		
		// If admin role we include the admin scripts.
		if($this->user->obj->role == 'admin') {
			$this->view->includeAdminScripts = true;
		} else {
			// No go.
			$this->view->includeAdminScripts = false;
		}
		
                // ei tämmöstä:
		// User can not go outside webroot so we fix the tpl param not to has goto up directory
		//$tpl = str_ireplace('../', '', $tpl);
                
                try
                   {
                     $this->validator->validateTpl($tpl);
                   }
                   catch(Exception $e)
                   {                     
                     $message = "Method: ".__METHOD__." ".$e->getMessage()."\n";
                     file_put_contents(ROOT.'/logs/ValidationErrors', $message, FILE_APPEND);   
                     die();
                   }
		
		// We render outer template, inject inner teplate to it
		return $this->view->render(ROOT . '/templates/outer/' . $tpl . '.phtml');
				
		// All is good.
		
	}

	
	
	
	
	
	
	
	
	
	
	
}