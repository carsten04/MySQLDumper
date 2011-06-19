<?php
/**
 * This file is part of MySQLDumper released under the GNU/GPL 2 license
 * http://www.mysqldumper.net
 *
 * @package    MySQLDumper
 * @subpackage SQL-Browser
 * @version    SVN: $Rev$
 * @author     $Author$
 */

require_once "Msd/Sql/Parser/Interface.php";
/**
 * Class to parse MySQL queries.
 * This enables you to analyze and modify MySQL queries, which the user has entered.
 *
 * @package         MySQLDumper
 * @subpackage      SQL-Browser
 */
class Msd_Sql_Parser implements Iterator
{
    /**
     * Saves the raw MySQL Query.
     *
     * @var string
     */
    private $_rawQuery = null;

    /**
     * Parsed MySQL statements.
     *
     * @var array
     */
    private $_parsedStatements = array();

    /**
     * Holds the summary of the parsing process.
     * The summary contains the count of each statement in the query.
     *
     * @var array
     */
    private $_parsingSummary = array();

    /**
     * MySQL comment types.
     *
     * @var array
     */
    protected $_sqlComments = array(
        '--' => "\n", '/*' => '*/', '/*!' => '*/'
    );

    /**
     * Whether to save debug output
     *
     * @var bool
     */
    private $_debug = false;

    /**
     * Debug output buffer
     *
     * @var string
     */
    private $_debugOutput = '';


    /**
     * Class constructor.
     * Creates a new instance of the MySQL parser and optionally assign the raw MySQL query.
     *
     * @param string $sqlQuery Raw MySQL query to parse
     * @param bool   $debug    If turned on, detection of queries is logged
     */
    public function __construct($sqlQuery = null, $debug = false)
    {
        if ($sqlQuery !== null) {
            $this->_rawQuery = $sqlQuery;
        }
        $this->_debug = $debug;
    }

    /**
     * Parses a raw MySQL query.
     * This could include more than one MySQL statement.
     *
     * @throws Msd_Sql_Parser_Exception
     *
     * @param string $sqlQuery Raw MySQL query to parse
     *
     * @return void
     */
    public function parse($sqlQuery = null)
    {
        if ($sqlQuery === null) {
            if ($this->_rawQuery === null) {
                throw new Msd_Sql_Parser_Exception('You must specify a MySQL query for parsing!');
            }
            $sqlQuery = $this->_rawQuery;
        }

        $sqlQuery = trim($sqlQuery);

        $statementCounter = 0;
        $startPos = 0;
        $queryLength = strlen($sqlQuery);
        while ($startPos < $queryLength) {
            $statementCounter++;
            // move pointer to the next character we can interprete
            while ($startPos < $queryLength && in_array($sqlQuery[$startPos], array(' ', "\n", "\r"))) {
                $startPos++;
            }

            $firstSpace = strpos($sqlQuery, ' ', $startPos);
            $statement = trim(strtolower(substr($sqlQuery, $startPos, $firstSpace - $startPos)));
            $lengthCheck = strlen($statement);
            if ($lengthCheck == 0) {
                break;
            }
            if ($lengthCheck == 1 || $statement{1} == ';' || $statement{1} == "\n") {
                $startPos = $startPos + 1;
                continue;
            }
            // check for comments or conditional comments
            $commentCheck = substr($statement, 0, 2);
            if (isset($this->_sqlComments[$commentCheck]) || substr($statement, 0, 3) == '/*!') {
                $commentEnd = $this->_sqlComments[$commentCheck];
                $endPos = strpos($sqlQuery, $commentEnd, $startPos) + strlen($commentEnd);
                $comment = substr($sqlQuery, $startPos, $endPos - $startPos);
                $this->_parseStatement($comment, 'Msd_Sql_Parser_Statement_Comment');
                $startPos = $endPos+1;
                continue;
            }

            $parserClass = 'Msd_Sql_Parser_Statement_' . ucwords($statement);
            $endPos = $this->_getStatementEndPos($sqlQuery, $startPos);
            $completeStatement = trim(substr($sqlQuery, $startPos, $endPos - $startPos));
            $startPos = $endPos + 1;
            $foundStatement = $this->_parseStatement($completeStatement, $parserClass);
            if ($this->_debug) {
                $this->_debugOutput .= '<br />Extracted statement: '.$foundStatement;
            }
            $this->_parsedStatements[] = $foundStatement;
            // increment query type counter
            if (!isset($this->_parsingSummary[$statement])) {
                $this->_parsingSummary[$statement] = 0;
            }
            $this->_parsingSummary[$statement]++;
        }
    }

    /**
     * Searches the end of a MySQL statement and returns its position.
     *
     * @param string $sqlQuery The complete MySQL query
     * @param int    $startPos Index, where to start the search.
     *
     * @return int End position of the statement
     */
    private function _getStatementEndPos($sqlQuery, $startPos = 0)
    {
        $nextString = strpos($sqlQuery, "'", $startPos);
        $nextSemicolon = strpos($sqlQuery, ';', $startPos);
        if ($nextString === false) {
            if ($nextSemicolon === false) {
                return strlen($sqlQuery);
            }

            return $nextSemicolon+1;
        }

        while ($nextString < $nextSemicolon) {
            $nextString = strpos($sqlQuery, "'", $nextString + 1);
            $nextSemicolon = strpos($sqlQuery, ';', $nextString + 1);
            $nextString = strpos($sqlQuery, "'", $nextString + 1);
            if ($nextString === false) {
                break;
            }
        }

        return $nextSemicolon;
    }

    /**
     * Creates an instance of a statement parser class and invokes statement parsing.
     *
     * @throws Msd_Sql_Parser_Exception
     *
     * @param string $statement   MySQL statement to parse
     * @param string $parserClass Parser class to use
     *
     * @return array
     */
    private function _parseStatement($statement, $parserClass)
    {
        try {
            $parserObject = new $parserClass;
        } catch (Exception $e) {
            throw new Msd_Sql_Parser_Exception("Can't suitable parser for the statement!");
        }

        return $parserObject->parse($statement);
    }

    /**
     * Returns the array with the parsed statements.
     *
     * @return array
     */
    public function getParsedStatements()
    {
        return $this->_parsedStatements;
    }

    /**
     * Returns the parsing summary.
     *
     * @return array
     */
    public function getSummary()
    {
        return $this->_parsingSummary;
    }

    /**
     * Rewind (reset) the internal pointer position af the parsed statements array.
     *
     * @return mixed
     */
    public function rewind()
    {
        return reset($this->_parsedStatements);
    }

    /**
     * Return the current value af the parsed statements array.
     *
     * @return mixed
     */
    public function current()
    {
        return current($this->_parsedStatements);
    }

    /**
     * Return the current key af the parsed statements array.
     *
     * @return mixed
     */
    public function key()
    {
        return key($this->_parsedStatements);
    }

    /**
     * Move the internal pointer af the parsed statements array to the next position.
     *
     * @return mixed
     */
    public function next()
    {
        return next($this->_parsedStatements);
    }

    /**
     * Validates the internal pointer position af the parsed statements array.
     *
     * @return bool
     */
    public function valid()
    {
        return key($this->_parsedStatements) !== null;
    }

    /**
     * Get debug output buffer
     *
     * @return array
     */
    public function getDebugOutput()
    {
        return $this->_debugOutput;
    }
}
