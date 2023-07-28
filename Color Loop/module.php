<?php

declare(strict_types=1);

// Allgemeine Funktionen
require_once __DIR__ . '/../libs/_traits.php';

/**
 * CLASS ColorLoop
 */
class ColorLoop extends IPSModule
{
    use ColorHelper;
    use DebugHelper;
    use ProfileHelper;
    use VariableHelper;

    // Transition Profil
    private $assoTransition = [
        [2, 'Fast', '', 0xFF00FF],
        [5, 'Normal', '', 0x0000FF],
        [8, 'Slow', '', 0x00FF00],
        [12, 'Very Slow', '', 0xFFFF00],
    ];

    /**
     * Overrides the internal IPSModule::Create($id) function
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        // Switch variable
        $this->RegisterPropertyInteger('StateVariable', 0);
        // Device list
        $this->RegisterPropertyString('ColorVariables', '[]');
        // Settings
        $this->RegisterPropertyBoolean('CheckColor', false);
        // Profiles
        $this->RegisterProfile(VARIABLETYPE_INTEGER, 'WWXCL.Increment', 'Speedo', '', '', 5, 355, 5, 0);
        $this->RegisterProfile(VARIABLETYPE_INTEGER, 'WWXCL.Transition', 'Repeat', '', '', 0, 0, 0, 0, $this->assoTransition);
        // Status variable (active)
        $exists = @$this->GetIDForIdent('active');
        $this->RegisterVariableBoolean('active', $this->Translate('Active'), '~Switch', 0);
        if($exists === false) {
            $this->SetValueBoolean('active', true);
        }
        $this->EnableAction('active');
        // Status variable (increment)
        $exists = @$this->GetIDForIdent('increment');
        $this->RegisterVariableInteger('increment', $this->Translate('Increment'), 'WWXCL.Increment', 1);
        if($exists === false) {
            $this->SetValueInteger('increment', 5);
        }
        $this->EnableAction('increment');
        // Status variable (transition)
        $exists = @$this->GetIDForIdent('transition');
        $vid = $this->RegisterVariableInteger('transition', $this->Translate('Transition'), 'WWXCL.Transition', 2);
        if($exists === false) {
            $this->SetValueInteger('transition', 5);
        }
        $this->EnableAction('transition');
        // Trigger
        $this->RegisterTimer('ColorLoopTrigger', 0, "IPS_RequestAction(\$_IPS['TARGET'],'cycle', 0);");
        // Buffer
        $this->SetBuffer('loop_data', '');
    }

    /**
     * Overrides the internal IPSModule::Destroy($id) function
     */
    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    /**
     * Overrides the internal IPSModule::ApplyChanges($id) function
     */
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        //Delete all references in order to readd them
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        //Delete all registrations in order to readd them
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }

        //Register references & update messages
        $variable = $this->ReadPropertyInteger('StateVariable');
        if (IPS_VariableExists($variable)) {
            $this->RegisterReference($variable);
            $this->RegisterMessage($variable, VM_UPDATE);
        } else {
            $this->SendDebug(__FUNCTION__, $variable . ' does not exist!');
            $this->SetStatus(201);
            return;
        }

        $json = $this->ReadPropertyString('ColorVariables');
        $this->SendDebug(__FUNCTION__, $json);
        $list = json_decode($json, true);

        $count = 0;
        $profile = true;
        $name = true;
        foreach ($list as &$line) {
            if (IPS_VariableExists($line['Variable'])) {
                $this->RegisterReference($line['Variable']);
                if($this->GetVariableProfile($line['Variable']) != '~HexColor') {
                    $this->SendDebug(__FUNCTION__, $line['Variable'] . 'has wrong Profile!');
                    $profile = false;
                }
                if(empty($line['Name'])) {
                    $this->SendDebug(__FUNCTION__, $line['Variable'] . 'has no Name!');
                    $name = false;
                }
                $count++;
            }
            else {
                $this->SendDebug(__FUNCTION__, $line['Variable'] . ' does not exist!');
                $this->SetStatus(204);
                return;
            }
        }
        // No lights
        if($count == 0) {
            $this->SetStatus(202);
            return;
        }
        // Wrong Profile
        if($profile != true) {
            $this->SetStatus(203);
            return;
        }

        $color = $this->ReadPropertyBoolean('CheckColor');
        if ($color && !$name) {
            $this->SetStatus(205);
            return;
        }
        // Color Vars
        $pos = 3;
        foreach ($list as &$line) {
            $ident = 'color_' . (string) $line['Variable'];
            $this->MaintainVariable($ident, $line['Name'], VARIABLETYPE_INTEGER, '~HexColor', $pos++, $color);
            if ($color) {
                $this->SetValueInteger($ident, $line['Color']);
                $this->EnableAction($ident);
            }
        }
        $this->SetStatus(102);
    }

    /**
     * Internal SDK funktion.
     * data[0] = new value
     * data[1] = value changed?
     * data[2] = old value
     * data[3] = timestamp.
     */
    public function MessageSink($timeStamp, $sender, $message, $data)
    {
        //$this->SendDebug(__FUNCTION__, 'SenderId: '.$sender.' Data: '.print_r($data, true), 0);
        switch ($message) {
            case VM_UPDATE:
                // ReceiverVariable
                if ($sender != $this->ReadPropertyInteger('StateVariable')) {
                    $this->SendDebug(__FUNCTION__, 'SenderID: ' . $sender . ' unknown!');
                } else {
                    if ($data[0] == true && $data[1] == true) { // OnChange on TRUE, i.e. motion detected
                        $this->SendDebug(__FUNCTION__, 'OnChange on TRUE - lights ON for ' . $sender);
                        $this->Switch(true);
                    } elseif ($data[0] == false && $data[1] == true) { // OnChange on FALSE, i.e. no motion
                        $this->SendDebug(__FUNCTION__, 'OnChange on FALSE - lights OFF for ' . $sender);
                        $this->Switch(false);
                    } else { // OnChange on FALSE, i.e. no change of status
                        $this->SendDebug(__FUNCTION__, 'OnChange unchanged - status not changed');
                    }
                }
                break;
        }
    }

    /**
     * RequestAction.
     *
     *  @param string $ident Ident.
     *  @param string $value Value.
     */
    public function RequestAction($ident, $value)
    {
        // Debug output
        $this->SendDebug(__FUNCTION__, $ident . ' => ' . $value);
        switch ($ident) {
            case 'cycle':
                $this->Cycle();
                break;
            case 'active':
                $this->SetValueBoolean($ident, $value);
                $this->Active($value);
                break;
            case 'increment':
                $this->SetValueInteger($ident, $value);
                break;
            case 'transition':
                $this->SetValueInteger($ident, $value);
                $this->Active($value);
                break;
            default:
                // should only be 'color_xxxxx'
                $this->SetValueInteger($ident, $value);
            break;
        }
        return true;
    }

    /**
     * Activated or deactivated the color loop functionality.
     *
     * @param bool $value False for transition otherwise true
     */
    private function Active($value)
    {
        $this->SendDebug(__FUNCTION__, $value);
        if($value) { // Acvitvate
            $vid = $this->ReadPropertyInteger('StateVariable');
            if (IPS_VariableExists($vid)) {
                $value = GetValue($vid); // no modul getvalue!!!
                if($value) {
                    $this->SendDebug(__FUNCTION__, 'Activate now!');
                    $this->Switch(true);
                } else {
                    $this->SendDebug(__FUNCTION__, 'No activation, only generally activated!');
                }
            }
        } else { // Deactivated
            $this->SendDebug(__FUNCTION__, 'Deactivate now!');
            $this->Switch(false);
        }
    }

    /**
     * Switch color loop ON or OFF.
     *
     * @param bool $value
     */
    private function Switch($value)
    {
        $this->SendDebug(__FUNCTION__, ($value ? 'true' : 'false'));
        if($value) { // ON
            $ison = $this->GetValue('active');
            // only if color loop is active switched!
            if($ison) {
                $tran = $this->GetValue('transition');
                $this->SendDebug(__FUNCTION__, 'Trans: ' . $tran);
                $ccol = $this->ReadPropertyBoolean('CheckColor');
                $json = $this->ReadPropertyString('ColorVariables');
                $list = json_decode($json, true);
                $data = [];
                foreach ($list as &$line) {
                    $varid = $line['Variable'];
                    $color = $line['Color']; // color from config
                    $ident = 'color_' . (string) $varid;
                    // color from status variable
                    if ($ccol) {
                        $color = $this->GetValue($ident);
                    }
                    // color is transparent then from light
                    if($color == -1) {
                        $color = GetValue($varid);
                    }
                    $data[] = [$varid, $color];
                }
                $this->SendDebug(__FUNCTION__, 'Data: ' . print_r($data, true), 0);
                $this->SetBuffer('loop_data', serialize($data));
                // Start Timer
                $this->SetTimerInterval('ColorLoopTrigger', $tran * 1000);
            }
        } else { // OFF
            $this->SetTimerInterval('ColorLoopTrigger', 0);
            $this->SetBuffer('loop_data', '');
        }
    }

    /**
     * Cycle
     *
     */
    private function Cycle()
    {
        $buffer = $this->GetBuffer('loop_data');
        $last = unserialize($buffer);
        $step = $this->GetValue('increment');
        $this->SendDebug(__FUNCTION__, 'Step size: ' . $step);
        $next = [];
        foreach($last as $index => $data) {
            $this->SendDebug(__FUNCTION__, '(1): ' . $data[1]);
            $rgb = $this->int2rgb($data[1]);
            $this->SendDebug(__FUNCTION__, '(2): ' . $rgb[0] . ', ' . $rgb[1] . ', ' . $rgb[2]);
            $hsl = $this->rgb2hsl($rgb[0], $rgb[1], $rgb[2]);
            $this->SendDebug(__FUNCTION__, '(3): ' . $hsl[0] . ', ' . $hsl[1] . ', ' . $hsl[2]);
            $hsl[0] = ($hsl[0] + $step) % 360;
            $this->SendDebug(__FUNCTION__, '(4): ' . $hsl[0] . ', ' . $hsl[1] . ', ' . $hsl[2]);
            $rgb = $this->hsl2rgb($hsl[0], $hsl[1], $hsl[2]);
            $this->SendDebug(__FUNCTION__, '(5): ' . $rgb[0] . ', ' . $rgb[1] . ', ' . $rgb[2]);
            $col = $this->rgb2int($rgb);
            $this->SendDebug(__FUNCTION__, '(6): ' . $col);
            $ret = RequestAction($data[0], $col);
            $next[] = [$data[0], $col];
        }
        //$this->SendDebug(__FUNCTION__, 'Data: ' . print_r($next, true), 0);
        $this->SetBuffer('loop_data', serialize($next));
    }
}