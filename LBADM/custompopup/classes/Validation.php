<?php
/**
 * Support contact : soporte@corecreativo.es.
 *
 * NOTICE OF LICENSE
 *
 * This source file is the property of Corecreativo
 * that is bundled with this package.
 * It is also available through the world-wide-web at this URL:
 * https://www.corecreativo.es/
 *
 * @category  front-end
 * @author    Corecreativo (http://www.corecreativo.es/)
 * @copyright 2016-2022 Corecreativo and contributors
 */

class Validation extends CustomPopup
{
    private $errors = array();

    public function validate($name, $field, $rules = array())
    {
        foreach ($rules as $key => $value) {
            switch ($key) {
                case 'notempty':
                    if (!$field || trim(Tools::strlen($field))<1) {
                        $this->setError(
                            $name,
                            sprintf(
                                $this->l("%s - no puede estar vacía."),
                                $name
                            )
                        );
                    }
                    break;

                case 'maxlength':
                    if (Tools::strlen($field) > $value) {
                        $this->setError(
                            $name,
                            sprintf(
                                $this->l("%s - value '%s' is too long. Maximum is %s characters."),
                                $name,
                                $field,
                                $value
                            )
                        );
                    }
                    break;

                case 'minlength':
                    if (Tools::strlen($field) < $value) {
                        $this->setError(
                            $name,
                            sprintf(
                                $this->l("%s - value '%s' is too short. Minimum is %s characters."),
                                $name,
                                $field,
                                $value
                            )
                        );
                    }
                    break;

                case 'isnumber':
                    if (!is_numeric($field)) {
                        $this->setError($name, sprintf($this->l("%s - value '%s' is not a number."), $name, $field));
                    }
                    break;

                case 'ishex':
                    if ((Tools::strlen($field) !=4 || Tools::strlen($field) != 7)
                        && Tools::substr($field, 0, 1)!="#") {
                        $this->setError(
                            $name,
                            sprintf(
                                $this->l("%s - value '%s' is not valid HEX color."),
                                $name,
                                $field
                            )
                        );
                    }
                    break;
            }
        }
    }

    private function setError($name, $msg)
    {
        $this->errors[$name][] = $msg;
    }

    public function getError($name)
    {
        return @$this->errors[$name];
    }

    public function getAllErrors()
    {
        return $this->errors;
    }
}
