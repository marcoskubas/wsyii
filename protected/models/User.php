<?php

/**
 * This is the model class for table "t010usr".
 *
 * The followings are the available columns in table 't010usr':
 * @property integer $id
 * @property string $nicusr
 * @property string $namusr
 * @property string $emlusr
 * @property string $pasusr
 * @property string $typaut
 * @property string $stsusr
 * @property string $chgpwd
 * @property string $imgusr
 * @property integer $lstact
 * @property string $datinc
 * @property string $acttrm
 */
class User extends CActiveRecord
{
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return User the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 't010usr';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('nicusr, namusr, emlusr, pasusr, typaut, stsusr, chgpwd', 'required'),
			/*
			array('managerId', 'numerical', 'integerOnly'=>true),
			array('email, title, department, officePhone, cellPhone, city, twitterId', 'length', 'max'=>45),
			array('password', 'length', 'max'=>10),
			array('firstName, lastName', 'length', 'max'=>30),
			array('picture', 'length', 'max'=>250),
			array('blogURL', 'length', 'max'=>200),
			*/
			// The following rule is used by search().
			// Please remove those attributes that should not be searched.
			array('ID, nicusr, namusr, emlusr, stsusr, chgpwd, acttrm', 'safe', 'on'=>'search'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		// NOTE: you may need to adjust the relation name and the related
		// class name for the relations automatically generated below.
		return array(
			'sessao' => array(self::HAS_ONE, 'UsuarioSessao', 'id010usr')
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'ID' => 'ID',
			'nicusr' => 'Apelido',
			'namusr' => 'Nome',
			'emlusr' => 'Email',
			'pasusr' => 'Password',
			'stsusr' => 'Ativo',
			'chgpwd' => 'Alterar senha login',
			'imgusr' => 'Imagem',
			'acttrm' => 'Aceitar termos'
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search()
	{
		// Warning: Please modify the following code to remove attributes that
		// should not be searched.

		$criteria=new CDbCriteria;

		$criteria->compare('ID',$this->ID);
		$criteria->compare('nicusr',$this->nicusr,true);
		$criteria->compare('namusr',$this->namusr,true);
		$criteria->compare('emlusr',$this->emlusr,true);
		$criteria->compare('stsusr',$this->stsusr,true);
		$criteria->compare('chgpwd',$this->chgpwd,true);
		$criteria->compare('acttrm',$this->acttrm,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}
}