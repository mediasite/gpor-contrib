<?php
class ElkaJoinForm extends CFormModel
{
    const THEME_GIFT = 10;
    const THEME_MONEY = 20;
    const THEME_QUESTION = 30;

	public $theme; // для одного срока
	public $comment; // минимальный срок для интервала сроков
	public $name; // максимальный срок для интервала сроков
	public $phone;
	public $email;

	public static function themeTypes()
	{
		return array(
			self::THEME_GIFT => 'Хочу купить подарок',
			self::THEME_MONEY => 'Могу помочь материально',
			self::THEME_QUESTION => 'Вопрос по проведению акции',
			);
	}
	
	public function rules()
    {
        return array(
			array('comment', 'safe' ),
			array('name', 'required', 'message' => 'Укажите ваше имя и фамилию' ),
            array('phone', 'required', 'message' => 'Укажите ваш телефон' ),
            array('email', 'required', 'message' => 'Укажите ваш электронный адрес' ),
            array('email', 'email', 'message' => 'Адрес электронной почты указан неверно. Пример: mymail@gmail.com' ),
		);
    }
    
    
    public function afterValidate()
    {
    	return parent::afterValidate();
    }

    public function attributeLabels()
    {
        return array(
        	'theme' => 'Тема письма',
        	'comment' => 'Комментарий',
        	'name' => 'Ваши имя и фамилия',
        	'phone' => 'Контактный телефон',
        	'email' => 'Контактный e-mail',
        );
    }

    public function getFormRenderData () {
        $elements = array(
            'elements' => array(),
            'enctype' => 'multipart/form-data',
            'elements' => $this->getFormElements(),
            'buttons' => $this->getButtons(),
        );
        return $elements;
    }

    public function getFormElements ()
    {
        $res = array(
            'theme' => array(
                'type' => 'dropdownlist',
                'items' => self::themeTypes(),
            ),
            'comment' => array(
                'type' => 'textarea',
            ),
            'name' => array(
                'type' => 'text',
            ),
            'phone' => array(
                'type' => 'text',
            ),
            'email' => array(
                'type' => 'text',
            ),
        );
        return $res;

    }

    public function getButtons()
    {
        $res = array();
        $res['submit'] = array(
            'type' => 'submit',
            'label'=> 'Отправить',
        );
        return $res;
    }

}
?>