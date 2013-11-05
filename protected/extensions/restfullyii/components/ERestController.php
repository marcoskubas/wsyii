<?php
class ERestController extends Controller
{
	Const APPLICATION_ID = 'REST';
	Const C404NOTFOUND = 'HTTP/1.1 404 Not Found';
	Const C401UNAUTHORIZED = 'HTTP/1.1 401 Unauthorized';
	Const C406NOTACCEPTABLE = 'HTTP/1.1 406 Not Acceptable';
	Const C201CREATED = 'HTTP/1.1 201 Created';
	Const C200OK = 'HTTP/1.1 200 OK';
	Const C500INTERNALSERVERERROR = 'HTTP/1.1 500 Internal Server Error';

	public $HTTPStatus = 'HTTP/1.1 200 OK';
	public $restrictedProperties = array();
	public $restFilter = array(); 
	public $restSort = array();
	public $restScenario = null;
	public $restLimit = '100'; // Default limit
	public $restOffset = 0; //Default Offset
	public $restField = ''; //Default Fields Select => '' == all
	public $developmentFlag = YII_DEBUG; //When set to `false' 500 errors will not return detailed messages.
	protected $httpsOnly= FALSE; // Setting this variable to true allows the service to be used only via https
	//Auto will include all relations 
	//FALSE will include no relations in response
	//You may also pass an array of relations IE array('posts', 'comments', etc..)
	//Override $nestedModels in your controller as needed
	public $nestedModels = 'auto';

	//By supplying a scenario you can determine which fields are included in a request
	public $scenario = 'rest';

	protected $requestReader;
	protected $model = null;

	public function __construct($id, $module = null) 
	{
		parent::__construct($id, $module);
		$this->requestReader = new ERequestReader('php://input');
	}

	public function beforeAction($event)
	{

		if(isset($_GET['filter']))
			$this->restFilter = $_GET['filter'];

		if(isset($_GET['field']))
			$this->restField = $_GET['field'];

		if(isset($_GET['sort']))
			$this->restSort = $_GET['sort'];

		if(isset($_GET['limit']))
			$this->restLimit = $_GET['limit'];

		if(isset($_GET['offset']))
			$this->restOffset = $_GET['offset'];

		if(isset($_GET['scenario']))
			$this->restScenario = $_GET['scenario'];

		return parent::beforeAction($event);
	}

	public function onException($event)
	{
		if(!$this->developmentFlag && ($event->exception->statusCode == 500 || is_null($event->exception->statusCode)))
			$message = "Internal Server Error";
		else
		{
			$message = $event->exception->getMessage();
			if($tempMessage = CJSON::decode($message))
				$message = $tempMessage;
		}

		$errorCode = (!isset($event->exception->statusCode) || is_null($event->exception->statusCode))? 500: $event->exception->statusCode;
		
		$this->renderJson(array('success' => false, 'message' => $message, 'data' => array('errorCode'=>$errorCode)));
		$event->handled = true;
	}
 
	public function filters() 
	{
		$restFilters = array('HttpsOnly','restAccessRules+ restList restView restCreate restUpdate restDelete');
		if(method_exists($this, '_filters'))
			return CMap::mergeArray($restFilters, $this->_filters());
		else
			return $restFilters;
	} 

 	/**
	 * @author Romina Suarez
	 *
	 * allows users to block any nonHttps request if they want their service to be safe
	 * If the attribute $httpsOnly is set in one of the controllers that extend ERestController,
	 * you can avoid a specific model from being using without a secure connection.
	 */
	public function filterHttpsOnly($c){			
		if ($this->httpsOnly){
			if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS']!='on'){
				Yii::app()->errorHandler->errorAction = '/' . $this->uniqueid . '/error';	
				throw new CHttpException(401, "You must use a secure connection");						
			}	
		}
		$c->run();
	}
	public function accessRules()
	{
		$restAccessRules = array(
			array(
				'allow',	// allow all users to perform 'index' and 'view' actions
				'actions'=>array('restList', 'restView', 'restCreate', 'restUpdate', 'restDelete', 'error'),
				'users'=>array('*'),
			)
		);

		if(method_exists($this, '_accessRules'))
			return CMap::mergeArray($restAccessRules, $this->_accessRules());
		else
			return $restAccessRules;
	}	

	/**
	 * Controls access to restfull requests
	 */ 
	public function filterRestAccessRules( $c )
	{
		//For requests from JS check that a user is logged in and call validateUser
		//validateUser can/should be overridden in your controller.
		if(!Yii::app()->user->isGuest && $this->validateAjaxUser($this->action->id)) 
			$c->run(); 
		else 
		{
			/*
			$Users = new Usuario();
			$result = $Users->find('email = :email AND tokusr = :tokusr', array(':email' => $_SERVER['X_'.self::APPLICATION_ID.'_USERNAME'], ':tokusr' => $_SERVER['X_'.self::APPLICATION_ID.'_TOKEN']));
			
			$Users = new User();
			$result = $Users->find('emlusr = :email AND pasusr = :tokusr', array(':email' => $_SERVER['X_'.self::APPLICATION_ID.'_USERNAME'], ':tokusr' => '85dd24e1aa1b7d54130ed288b6460195'));
			*/

			$criteria=new CDbCriteria;
			$criteria->compare('emlusr', $_SERVER['X_'.self::APPLICATION_ID.'_USERNAME']);
			$criteria->compare('sessao.tokusr', $_SERVER['X_'.self::APPLICATION_ID.'_TOKEN']);
			$criteria->with = 'sessao';
			$result = new CActiveDataProvider('User', array(
				'criteria'=>$criteria,
			));

			if($result){
				$c->run();
			}elseif(empty($_SERVER['X_'.self::APPLICATION_ID.'_USERNAME'])){
				Yii::app()->clientScript->reset(); //Remove any scripts registered by Controller Class
				Yii::app()->onException = array($this, 'onException'); //Register Custom Exception

				if(Yii::app()->session['isLogged']){ 
					$c->run(); 
				}else{
					throw new CHttpException(401, 'Você não tem acesso a este recurso');
				}
			}else{
				Yii::app()->errorHandler->errorAction = '/' . $this->uniqueid . '/error';
				if(!(isset($_SERVER['X_'.self::APPLICATION_ID.'_USERNAME']) and isset($_SERVER['X_'.self::APPLICATION_ID.'_PASSWORD']))) {
					throw new CHttpException(401, 'You are not teste authorized to preform this action.');
				}
				$username = trim($_SERVER['X_'.self::APPLICATION_ID.'_USERNAME']);
				$password = trim($_SERVER['X_'.self::APPLICATION_ID.'_PASSWORD']);

				$this->checkCredentials($username, $password);

				// This tells the filter chain $c to keep processing.
				$c->run(); 
			}
		}
	}	

	/**
	 * Custom error handler for restfull Errors
	 */ 
	public function actionError()
	{
		if($error=Yii::app()->errorHandler->error)
		{
			if(!Yii::app()->request->isAjaxRequest)
				$this->HTTPStatus = $this->getHttpStatus($error['code'], 'C500INTERNALSERVERERROR');
			else if(!$this->developmentFlag)
			{
				if($error['code'] == 500)
					$error['message'] = 'Internal Server Error';
			}

			$this->renderJson(array('success' => false, 'message' => $error['message'], 'data' => array('errorCode'=>$error['code'])));
		}
	}

	/**
	 * Get HTTP Status Headers From code
	 */ 
	public function getHttpStatus($statusCode, $default='C200OK')
	{
		$httpStatus = new EHttpStatus($statusCode);
		if ($httpStatus->message) {
			return $httpStatus->__toString();
		} else { //Backward compatibility.
			switch ($statusCode)
			{
				case '200':
					return self::C200OK;
					break;
				case '201':
					return self::C201CREATED;
					break;
				case '401':
					return self::C401UNAUTHORIZED;
					break;
				case '404':
					return self::C404NOTFOUND;
					break;
				case '406':
					return self::C406NOTACCEPTABLE;
					break;
				case '500':
					return self::C500INTERNALSERVERERROR;
					break;
				default:
					return self::$default;
			}
		}
	}

	protected function getNestedRelations()
	{
		$nestedRelations = array();
		if(!is_array($this->nestedModels) && $this->nestedModels == 'auto')
		{
			foreach($this->model->metadata->relations as $rel=>$val)
			{
				$className = $val->className;
				$model = call_user_func(array($className, 'model'));
				if(!is_array($model->tableSchema->primaryKey))
					$nestedRelations[] = $rel;
			}
			return $nestedRelations;
		}
		else if(!is_array($this->nestedModels) && $this->nestedModels === false)
			return $nestedRelations;
		else if(is_array($this->nestedModels))
			return $this->nestedModels;
			
		return $nestedRelations;
	} 

 /**
	****************************************************************************************** 
	******************************************************************************************
	* Actions that are triggered by RESTFull requests
	* To change their default behavior 
	* you should override "doRest..." Methods in the controller 
	* and leave these actions as is
	******************************************************************************************
	******************************************************************************************
	 */

	/**
	 * Renders list of data associated with controller as json
	 */
	public function actionRestList() 
	{
		$this->doRestList();
	}
	
	/**
	 * Renders View of record as json
	 * Or Custom method
	 */ 
	public function actionRestView($id, $var=null, $var2=null)
	{
		if($this->isPk($id) && is_null($var)){
			$this->doRestView($id);
		}
		else
		{
			if($this->isPk($id) && !is_null($var) && is_null($var2))
			{
				if($this->validateSubResource($var))
					$this->doRestViewSubResource($id, $var);
				else
					$this->triggerCustomRestGet(ucFirst($var), array($id));
			}
			else if($this->isPk($id) && !is_null($var) && !is_null($var2))
			{
				if($this->validateSubResource($var))
					$this->doRestViewSubResource($id, $var, $var2);
				else
					$this->triggerCustomRestGet(ucFirst($var), array($id, $var2));
			}
			else
			{
				//if the $id is not numeric and var + var2 are not set
				//we are assume that the client is attempting to call a custom method
				//There may optionaly be a second param `$var` passed in the url
				$this->triggerCustomRestGet(ucFirst($id), array($var, $var2));
			}
		}
	}

	/**
	 * Updated record
	 */ 
	public function actionRestUpdate($id, $var=null, $var2=null)
	{
		$this->HTTPStatus = $this->getHttpStatus('200');
			
		if($this->isPk($id))
		{
			if(is_null($var))
				$this->doRestUpdate($id, $this->data());
			else if (is_null($var2))
				$this->triggerCustomRestPut($var, array($id));
			else if(!is_null($var2))
			{
				if($this->validateSubResource($var))
					$this->doRestUpdateSubResource($id, $var, $var2);
				else
					$this->triggerCustomRestPut($var, array($id, $var2));
			} 
		}
		else
			$this->triggerCustomRestPut($id, array($var, $var2));
	}
	

	/**
	 * Creates new record
	 */ 
	public function actionRestCreate($id=null, $var=null) 
	{
		$this->HTTPStatus = $this->getHttpStatus('201');

		if(!$id) 
		{
			//$this->doRestCreate($_POST);
			$this->doRestCreate($this->data()); //alterado
		}
		else
		{
			//we can assume if $id is set and var is not a subresource
			//then the user is trying to call a custom method
			$var = 'doCustomRestPost' . ucfirst($id);
			if(method_exists($this, $var))
				$this->$var($this->data());
			else if($this->isPk($var))
				$this->doRestCreate($this->data());
			else
				throw new CHttpException(500, 'Method or Sub-Resource does not exist.');
		}
	}

	/**
	 * Deletes record
	 */ 
	public function actionRestDelete($id, $var=null, $var2=null)
	{
		if($this->isPk($id))
		{
			if(is_null($var))
				$this->doRestDelete($id);
			else if(!is_null($var2))
			{
				if($this->validateSubResource($var, $var2))
					$this->doRestDeleteSubResource($id, $var, $var2); //Looks like we are trying to delete a subResource
				else
					$this->triggerCustomDelete($var, array($id, $var2));
			}
			else 
				$this->triggerCustomDelete($var, array($id));
		}
		else
		{
			$this->triggerCustomDelete($id, array($var, $var2));
		}
	}

	 /**
	****************************************************************************************** 
	******************************************************************************************
	* Helper functions for processing Rest data 
	******************************************************************************************
	******************************************************************************************
	 */
	
	/**
	 * Takes array and renders Json String
	 */ 
	protected function renderJson($data) {
		$this->layout = 'ext.restfullyii.views.layouts.json';
		$this->render('ext.restfullyii.views.api.output', array('data'=>$data));
	}


	/**
	 * Get data submitted by the client
	 */ 
	public function data() 
	{
		$request = $this->requestReader->getContents();
		if ($request) {
			return CJSON::decode($request);
		}
		return false;
	}

	/**
	 * Returns the model associated with this controller.
	 * The assumption is that the model name matches your controller name
	 * If this is not the case you should override this method in your controller
	 */ 
	public function getModel() 
	{
		if ($this->model === null) 
		{
			$modelName = str_replace('Controller', '', get_class($this)); 
			$this->model = new $modelName;
		}
		$this->_attachBehaviors($this->model);

		if(!is_null($this->restScenario)) {
			$this->model->scenario = $this->restScenario;
		}

		return $this->model;
	}

	/**
	* Helper for loading a single model
	*/
	protected function loadOneModel($id, $nested=true)
	{
		$return = "";
		if($nested)
			$return = $this->getModel()->with($this->nestedRelations)->findByPk($id);
		else
			$return = $this->getModel()->findByPk($id);

		if(!empty($this->restField)){
			$criteria = new CDbCriteria();
        	$criteria->select = Functions::JSONdecode($this->restField);
        	$criteria->condition = " ID = {$id}";
			$return = $this->getModel()->findAll($criteria);
		}

		return $return;
	}

	
	//Updated setModelAttributes to allow for related data to be set.
	private function setModelAttributes($model, $data)
	{
		foreach($data as $var=>$value) 
		{
			if(($model->hasAttribute($var) || isset($model->metadata->relations[$var])) && !in_array($var, $this->restrictedProperties)) {
				$model->$var = $value;
			}
			else {
				throw new CHttpException(406, 'Parameter \'' . $var . '\' is not allowed for model (' . get_class($model) . ')');
				
			}
		}
		
		return $model;
	}
	
	/**
	 * Helper for saving single/multiple models 
	 */ 
	protected function saveModel($model, $data) {
		$return_array = true;
		if (empty($data)) {
				$this->HTTPStatus = $this->getHttpStatus(406);
				throw new CHttpException(406, 'Model could not be saved as empty data.');
		}

		if (!isset($data[0])) {
			$models[] = $this->setModelAttributes($model, $data);
			$return_array = false;
		}
		else {
				for ($i = 0; $i < count($data); $i++) {
						$models[$i] = $this->setModelAttributes($this->getModel(), $data[$i]);
						$this->model = null;
				}
		}

		for ($cnt = 0; $cnt < count($models); $cnt++) {
				$this->_attachBehaviors($models[$cnt]);
				if ($models[$cnt]->validate()) {
						if (!$models[$cnt]->save()) {
								$this->HTTPStatus = $this->getHttpStatus(406);
								throw new CHttpException(406, 'Model could not be saved');
						}
						else {
							$ids[] = $models[$cnt]->{$models[$cnt]->tableSchema->primaryKey};
						}
				}else {
						$message = CJSON::encode(array('error' => 'Model could not be saved as validation failed.',
												'validation' => $models[$cnt]->getErrors()));

						$this->HTTPStatus = $this->getHttpStatus(406);
						throw new CHttpException(406, $message);
				}
		}
		if($return_array) {
			return $this->getModel()->with($this->getNestedRelations())->findAllByPk($ids);
		}
		else
			return $this->getModel()->with($this->getNestedRelations())->findAllByPk($ids[0]);
	}

    //Attach helper behaviors
	public function _attachBehaviors($model)
	{
		//Attach this behavior to help saving nested models
		if(!array_key_exists('EActiveRecordRelationBehavior', $model->behaviors()))
			$model->attachBehavior('EActiveRecordRelationBehavior', new EActiveRecordRelationBehavior());

		//Attach this behavior to help outputting models and their relations as arrays
		if(!array_key_exists('MorrayBehavior', $model->behaviors()))
			$model->attachBehavior('MorrayBehavior', new MorrayBehavior());

		if(!array_key_exists('ERestHelperScopes', $model->behaviors()))
			$model->attachBehavior('ERestHelperScopes', new ERestHelperScopes());

		return true;
	}


	/**
	 *  Convert list of models or single model to array
	 */ 
	public function allToArray($models)
	{
		$options = array('scenario' => $this->scenario);
		
		if(is_array($models))
		{
			$results = array();
			foreach($models as $model)
			{
				$this->_attachBehaviors($model);
				$results[] = $model->toArray($options);
			}
				return $results;
		}
		else if($models != null)
		{
			$this->_attachBehaviors($models);
			return $models->toArray($options);
		}
		else
			return array();
	}

	public function triggerCustomRestGet($id, $vars=array())
	{
		$method = 'doCustomRestGet' . ucfirst($id);
		if(method_exists($this, $method))
			$this->$method($vars);
		else
			throw new CHttpException(500, 'Method or Sub-Resource does not exist.');
	}

	public function triggerCustomRestPut($method, $vars=array())
	{
		$method = 'doCustomRestPut' . ucfirst($method);
		
		if(method_exists($this, $method))
		{
			if(count($vars) > 0)
				$this->$method($this->data(), $vars);
			else
				$this->$method($this->data());
		}
		else
    		{
        		throw new CHttpException(500, 'Method or Sub-Resource does not exist.');
    		}	
	}

	public function triggerCustomDelete($methodName, $vars=array())
	{
		$method = 'doCustomRestDelete' . ucfirst($methodName);
		if(method_exists($this, $method))
			$this->$method($vars);
		else
			throw new CHttpException(500, 'Method or Sub-Resource does not exist.');
	}

	public function validateSubResource($subResourceName, $subResourceID=null)
	{
		if(is_null($relations = $this->getModel()->relations()))
			return false;
		if(!isset($relations[$subResourceName]))
			return false;
		if($relations[$subResourceName][0] != CActiveRecord::MANY_MANY)
			return false;
		if(!is_null($subResourceID))
			return $subResourceID === '0' || preg_match('/^-?[1-9][0-9]*$/', $subResourceID) === 1;

		return true;
	}

	public function getSubResource($subResourceName)
	{
		$relations = $this->getModel()->relations();
		return $this->getModel()->parseManyManyFk($subResourceName, $relations[$subResourceName]);
	}

	/**
	****************************************************************************************** 
	******************************************************************************************
	* OVERRIDE THE METHODS BELOW IN YOUR CONTROLLER TO REMOVE/ALTER DEFAULT FUNCTIONALITY
	******************************************************************************************
	******************************************************************************************
	 */
	
	/**
	 * Override this function if your model uses a non Numeric PK.
	 */
	public function isPk($pk) 
	{
		return $pk === '0' || preg_match('/^-?[1-9][0-9]*$/', $pk) === 1;
	} 

	/**
	 * Override this function if you need to check user credentials
	 * in different way
	 */
	public function checkCredentials($username, $password)
	{
		// Find the user
		if($username != Yii::app()->params['RESTusername'])
		{
			// Error: Unauthorized
			throw new CHttpException(401, 'Error: User Name is invalid');
		}
		else if($password != Yii::app()->params['RESTpassword'])
		{
			// Error: Unauthorized
			throw new CHttpException(401, 'Error: User Password is invalid');
		}
	}

	/**
	 * You should override this method to provide stronger access control 
	 * to specific restfull actions via AJAX
	 */ 
	public function validateAjaxUser($action)
	{
		return false;
	}

	public function outputHelper($message, $results, $totalCount=0, $model=null)
	{
		if(is_null($model))
			$model = get_class($this->model);

		$model = strtolower(substr($model,0,1)).substr($model,1);

		if(!empty($this->restField)){
			$fields = Functions::JSONdecode($this->restField);
			$newResult = array();
	        foreach ($results as $key => $Mod) {
	            foreach ($Mod->attributes as $campo => $value) {
	                if(in_array($campo, $fields)){
	                   $newResult[$key][$campo] = $value;
	                }
	            }
	        }
	        $results = $newResult;
		}else{
			$results = $this->allToArray($results);
		}

		$this->renderJson(array(
			'success'=>true, 
			'message'=>$message, 
			'data'=>array(
				'totalCount'=>$totalCount, 
				$model=>$results
			)
		));
	}

	/**
	 * This is broken out as a separate method from actionRestList 
	 * To allow for easy overriding in the controller
	 * and to allow for easy unit testing
	 */ 
	public function doRestList()
	{
		# Implements Metacem 
		$tableName = $this->getModel()->tableSchema->name;
		$dependency = new CDbCacheDependency("SELECT MAX(ID) FROM {$tableName}");
		# /Implements Metacem
		$this->outputHelper( 
			'Records Retrieved Successfully', 
			$this->getModel()->with($this->nestedRelations)->cache(180000, $dependency)
				->fieldBy($this->restField)->filter($this->restFilter)->orderBy($this->restSort)
				->limit($this->restLimit)->offset($this->restOffset)
			->findAll(),
			$this->getModel()
				->with($this->nestedRelations)
				->filter($this->restFilter)
			->count()
		);
	}
	
	/**
	 * This is broken out as a separate method from actionRestView
	 * To allow for easy overriding in the controller
	 * adn to allow for easy unit testing
	 */ 
	public function doRestViewSubResource($id, $subResource, $subResourceID=null)
	{
		$subResourceRelation = $this->getModel()->getActiveRelation($subResource);
		$subResourceModel = new $subResourceRelation->className;
		$this->_attachBehaviors($subResourceModel);

		if(is_null($subResourceID))
		{
			$modelName = get_class($this->model);
			$newRelationName = "_" . $subResourceRelation->className . "Count";
			$this->getModel()->metaData->addRelation($newRelationName, array(
				constant($modelName.'::STAT'), $subResourceRelation->className, $subResourceRelation->foreignKey
			));

			$model = $this->getModel()->with($newRelationName)->findByPk($id);
			$count = $model->$newRelationName;

			$results = $this->getModel()
				->with($subResource)
				->limit($this->restLimit)
				->offset($this->restOffset)
			->findByPk($id, array('together'=>true));

			$results = $results->$subResource;

			$this->outputHelper(
				'Records Retrieved Successfully', 
				$results,
				$count,
				$subResourceRelation->className
			);
		}
		else
		{		
			$results = $this->getModel()
				->with($subResource)
				->findByPk($id, array('condition'=>"$subResource.id=$subResourceID"));

			if(is_null($results))
			{
				$this->HTTPStatus = 404;
				throw new CHttpException('404', 'Record Not Found');
			}

			$this->outputHelper(
				'Record Retrieved Successfully', 
				$results->$subResource,
				1,
				$subResourceRelation->className
			);
		}
	}

	 /**
	 * This is broken out as a separate method from actionRestView
	 * To allow for easy overriding in the controller
	 * adn to allow for easy unit testing
	 */ 
	public function doRestView($id)
	{
		$model = $this->loadOneModel($id);
		
		if(is_null($model))
		{
			$this->HTTPStatus = 404;
				throw new CHttpException('404', 'Record Not Found');
		}
	
		$this->outputHelper(
			'Record Retrieved Successfully', 
			$model,
			1
		);
	}

	/**
	 * This is broken out as a separate method from actionResUpdate 
	 * To allow for easy overriding in the controller
	 * and to allow for easy unit testing
	 */ 
    public function doRestUpdate($id, $data) {
			$model = $this->loadOneModel($id, false);
			if (is_null($model)) {
					$this->HTTPStatus = $this->getHttpStatus(404);
					throw new CHttpException(404, 'Record Not Found');
			} else {
					$model = $this->saveModel($this->loadOneModel($id,false), $data);
					$this->outputHelper(
						'Record Updated', $this->loadOneModel($id), 1
					);
			}
    }
	
	/**
	 * This is broken out as a separate method from actionRestCreate 
	 * To allow for easy overriding in the controller
	 * and to allow for easy unit testing
	 */ 
	public function doRestCreate($data) 
	{
		$models = $this->saveModel($this->getModel(), $data);
		//$this->renderJson(array('success'=>true, 'message'=>'Record(s) Created', 'data'=>array($models)));
		$this->outputHelper(
			'Record(s) Created',
			$models,
			count($models)
		);
	}

	/**
	 * This is broken out as a separate method from actionRestCreate 
	 * To allow for easy overriding in the controller
	 * and to allow for easy unit testing
	 */
	public function doRestUpdateSubResource($id, $subResource, $subResourceID)
	{
		list($relationTable, $fks) = $this->getSubResource($subResource);
		if($this->saveSubResource($id, $subResourceID, $relationTable, $fks) > 0)
		{
			$this->renderJson(
				array('success'=>true, 'message'=>'Sub-Resource Added', 'data'=>array(
					$fks[0] => $id,
					$fks[1] => $subResourceID,
				))
			);
		}
		else
			throw new CHttpException('500', 'Could not save Sub-Resource');
		
	}

	public function saveSubResource($pk, $fk, $relationTable, $fks)
	{
		return $this->getModel()->dbConnection->commandBuilder->createInsertCommand($relationTable, array(
			$fks[0] => $pk,
			$fks[1] => $fk,
		))->execute();
	}
	
	/**
	 * This is broken out as a separate method from actionRestDelete 
	 * To allow for easy overriding in the controller
	 * and to allow for easy unit testing
	 */ 
	public function doRestDelete($id) {
        $model = $this->loadOneModel($id);
        if (is_null($model)) {
            $this->HTTPStatus = $this->getHttpStatus(404);
            throw new CHttpException(404, 'Record Not Found');
        } else {
            if ($model->delete())
                $data = array('success' => true, 'message' => 'Record Deleted', 'data' => array('id' => $id));
            else {
                $this->HTTPStatus = $this->getHttpStatus(406);
                throw new CHttpException(406, 'Could not delete model with ID: ' . $id);
            }
            $this->renderJson($data);
        }
    }
	
	/**
	 * This is broken out as a separate method from actionRestDelete 
	 * To allow for easy overriding in the controller
	 * and to allow for easy unit testing
	 */ 
	public function doRestDeleteSubResource($id, $subResource, $subResourceID)
	{
		list($relationTable, $fks) = $this->getSubResource($subResource);
		$criteria=new CDbCriteria();
		$criteria->addColumnCondition(array(
			$fks[0]=>$id,
			$fks[1]=>$subResourceID
		));
		if($this->getModel()->dbConnection->commandBuilder->createDeleteCommand($relationTable, $criteria)->execute())
		{
			$data = array('success'=>true, 'message'=>'Record Deleted', 'data'=>array(
				$fks[0]=>$id,
				$fks[1]=>$subResourceID
			));
		}
		else
		{
			throw new CHttpException(406, 'Could not delete model with ID: ' . array(
				$fks[0]=>$id,
				$fks[1]=>$subResourceID
			));
		}
		
		$this->renderJson($data);
	}

	public function setRequestReader($requestReader) 
	{
		$this->requestReader = $requestReader;
	}

	public function setModel($model) 
	{
		$this->model = $model;
	}
}
