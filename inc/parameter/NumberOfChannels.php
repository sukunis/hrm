<?php
/**
 * NumberOfChannels
 *
 * @package hrm
 * @subpackage param
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

require_once dirname(__FILE__) . '/../bootstrap.inc.php';

/**
 * Class NumberOfChannels
 *
 * A ChoiceParameter to represent the number of channels.
 *
 * @package hrm\param
 */
class NumberOfChannels extends ChoiceParameter
{

    /**
     * NumberOfChannels constructor.
     */
    public function __construct()
    {
        parent::__construct("NumberOfChannels");
    }

    /**
     * Confirms that this is an Image Parameter.
     * @return bool Always True.
     */
    public function isForImage()
    {
        return True;
    }

}
