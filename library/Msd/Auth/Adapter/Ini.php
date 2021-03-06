<?php
/**
 * This file is part of MySQLDumper released under the GNU/GPL 2 license
 * http://www.mysqldumper.net
 *
 * @package         MySQLDumper
 * @subpackage      Auth_adapter
 * @version         SVN: $Rev$
 * @author          $Author$
 */
/**
 * Adapter to use Zend_Auth with INI files.
 *
 * @package         MySQLDumper
 * @subpackage      Auth_adapter
 */
class Msd_Auth_Adapter_Ini implements Zend_Auth_Adapter_Interface
{
    /**
     * User database
     *
     * @var array
     */
    private $_users = null;

    /**
     * Username for authentication.
     *
     * @var string
     */
    private $_username = null;

    /**
     * Password for authentication.
     *
     * @var string
     */
    private $_password = null;

    /**
     * Class constructor
     *
     * @param string $iniFilename Filename for registered users
     *
     * @throws Msd_Exception
     *
     * @return Msd_Auth_Adapter_Ini
     */
    public function __construct($iniFilename)
    {
        if (!file_exists($iniFilename)) {
            throw new Msd_Exception(
                'INI file with authentication information doesn\'t exists!'
            );
        }
        $this->_users = parse_ini_file($iniFilename, true);
    }

    /**
     * set the username, which is used for authentication.
     *
     * @param string $username The username
     *
     * @return void
     */
    public function setUsername($username)
    {
        $this->_username = (string)$username;
    }

    /**
     * Set the password, which is used for authentication.
     *
     * @param string $password The password
     *
     * @return void
     */
    public function setPassword($password)
    {
        $this->_password = (string)$password;
    }

    /**
     * Authenticate with the given credentials.
     *
     * @throws Msd_Exception
     *
     * @return Zend_Auth_Result
     */
    public function authenticate()
    {
        if ($this->_username == null || $this->_password == null) {
            throw new Msd_Exception(
                'You must set the username and password first!'
            );
        }

        $authResult = false;
        foreach ($this->_users['users']['user'] as $name => $pass) {
            if ($this->_username == $name && md5($this->_password) == $pass) {
                $authResult = array(
                    'id'   => $name,
                    'name' => $name,
                );
            }
        }

        if ($authResult === false) {
            return new Zend_Auth_Result(
                Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID,
                array(),
                array('L_LOGIN_INVALID_USER')
            );
        }

        return new Zend_Auth_Result(
            Zend_Auth_Result::SUCCESS,
            $authResult
        );
    }
}
