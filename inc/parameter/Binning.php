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
 * Class Binning
 *
 * A ChoiceParameter to represent the binning.
 *
 * @package hrm\param
 */
class Binning extends ChoiceParameter
{

    /**
     * Binning constructor.
     */
    public function __construct()
    {
        parent::__construct("Binning");
    }

    /**
     * Confirms that this is a Calculation Parameter.
     * return bool Always true.
     */
    public function isForPixelSizeCalculation()
    {
        return True;
    }

}
