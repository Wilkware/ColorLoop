<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/_traits.php'; // Generell funktions

/**
 * CLASS ColorLoop
 */
class ColorLoop extends IPSModuleStrict
{
    use ColorHelper;
    use DebugHelper;
    use FormatHelper;
    use VariableHelper;

    /**
     * @var array<string,mixed> Increment Presentation (Value)
     */
    private const CL_PRESENTATION_INCREMENT = [
        'PRESENTATION'        => VARIABLE_PRESENTATION_SLIDER,
        'USAGE_TYPE'          => 5,
        'THOUSANDS_SEPARATOR' => '',
        'DECIMAL_SEPARATOR'   => 'Client',
        'PERCENTAGE'          => false,
        'DIGITS'              => 0,
        'INTERVALS'           => '[]',
        'ICON'                => 'dial-med',
        'INTERVALS_ACTIVE'    => false,
        'MAX'                 => 355,
        'GRADIENT_TYPE'       => 0,
        'MIN'                 => 5,
        'CUSTOM_GRADIENT'     => '[]',
        'PREFIX'              => '',
        'STEP_SIZE'           => 5.0,
        'SUFFIX'              => '°',
    ];

    /**
     * @var array<string,mixed> Transition Presentation (Slider)
     */
    private const CL_PRESENTATION_TRANSITION = [
        'PRESENTATION'        => VARIABLE_PRESENTATION_SLIDER,
        'USAGE_TYPE'          => 5,
        'THOUSANDS_SEPARATOR' => '',
        'DECIMAL_SEPARATOR'   => 'Client',
        'PERCENTAGE'          => false,
        'DIGITS'              => 0,
        'INTERVALS'           => '[]',
        'ICON'                => 'clock',
        'INTERVALS_ACTIVE'    => false,
        'MAX'                 => 20,
        'GRADIENT_TYPE'       => 0,
        'MIN'                 => 2,
        'CUSTOM_GRADIENT'     => '[]',
        'PREFIX'              => '',
        'STEP_SIZE'           => 1.0,
        'SUFFIX'              => 's',
    ];

    /**
     * @var array<string,mixed> Switch Presentation (Switch)
     */
    private const CL_PRESENTATION_SWITCH = [
        'PRESENTATION'   => VARIABLE_PRESENTATION_SWITCH,
        'USE_ICON_FALSE' => false,
        'USAGE_TYPE'     => 0,
        'ICON_TRUE'      => 'power-off',
        'ICON_FALSE'     => 'power-off',
        'GLOW_INTENSITY' => 50,
        'GLOW_COLOR'     => 16771899,
    ];

    /**
     * @var array<string,mixed> Autostart Presentation (Switch)
     */
    private const CL_PRESENTATION_AUTOSTART = [
        'PRESENTATION'   => VARIABLE_PRESENTATION_SWITCH,
        'USE_ICON_FALSE' => false,
        'USAGE_TYPE'     => 0,
        'ICON_TRUE'      => 'bolt',
        'ICON_FALSE'     => 'bolt',
        'GLOW_INTENSITY' => 50,
        'GLOW_COLOR'     => 16771899,
    ];

    /**
     * @var array<string,mixed> Resume Presentation (Switch)
     */
    private const CL_PRESENTATION_RESUME = [
        'PRESENTATION'   => VARIABLE_PRESENTATION_SWITCH,
        'USE_ICON_FALSE' => false,
        'USAGE_TYPE'     => 0,
        'ICON_TRUE'      => 'arrow-rotate-right',
        'ICON_FALSE'     => 'arrow-rotate-right',
        'GLOW_INTENSITY' => 50,
        'GLOW_COLOR'     => 16771899,
    ];

    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting IP-Symcon.
     * Therefore, status variables and module properties which the module requires permanently should be created here.
     *
     * @return void
     */
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        // Switch variable
        $this->RegisterPropertyInteger('StateVariable', 1);
        // Device list
        $this->RegisterPropertyString('ColorVariables', '[]');
        // Settings
        $this->RegisterPropertyBoolean('CheckColor', false);

        // Status variable (active)
        $exists = @$this->GetIDForIdent('active');
        $this->RegisterVariableBoolean('active', $this->Translate('Active'), self::CL_PRESENTATION_SWITCH, 0);
        if (!IPS_VariableExists($exists)) {
            $this->SetValueBoolean('active', true);
        }
        $this->EnableAction('active');

        // Status variable (increment)
        $exists = @$this->GetIDForIdent('increment');
        $this->RegisterVariableInteger('increment', $this->Translate('Increment'), self::CL_PRESENTATION_INCREMENT, 1);
        if (!IPS_VariableExists($exists)) {
            $this->SetValueInteger('increment', 5);
        }
        $this->EnableAction('increment');

        // Status variable (transition)
        $exists = @$this->GetIDForIdent('transition');
        $vid = $this->RegisterVariableInteger('transition', $this->Translate('Transition'), self::CL_PRESENTATION_TRANSITION, 2);
        if (!IPS_VariableExists($exists)) {
            $this->SetValueInteger('transition', 5);
        }
        $this->EnableAction('transition');

        // Autostart variable (autostart)
        $this->RegisterVariableBoolean('autostart', $this->Translate('Autostart'), self::CL_PRESENTATION_AUTOSTART, 3);
        $this->EnableAction('autostart');

        // Resume variable (resume)
        $this->RegisterVariableBoolean('resume', $this->Translate('Resume'), self::CL_PRESENTATION_RESUME, 4);
        $this->EnableAction('resume');

        // Trigger
        $this->RegisterTimer('ColorLoopTrigger', 0, "IPS_RequestAction(\$_IPS['TARGET'],'cycle', 0);");

        // Buffer
        $this->SetBuffer('loop_data', '');

        // Set visualization type to 1, as we want to offer HTML
        $this->SetVisualizationType(1);
    }

    /**
     * This function is called when deleting the instance during operation and when updating via "Module Control".
     * The function is not called when exiting IP-Symcon.
     *
     * @return void
     */
    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    /**
     * The content can be overwritten in order to transfer a self-created configuration page.
     * This way, content can be generated dynamically.
     * In this case, the "form.json" on the file system is completely ignored.
     *
     * @return string Content of the configuration page.
     */
    public function GetConfigurationForm(): string
    {
        // Get Form
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // Extract Version
        $ins = IPS_GetInstance($this->InstanceID);
        $mod = IPS_GetModule($ins['ModuleInfo']['ModuleID']);
        $lib = IPS_GetLibrary($mod['LibraryID']);
        $form['actions'][0]['items'][2]['caption'] = sprintf('v%s.%d', $lib['Version'], $lib['Build']);
        // Debug output
        //$this->LogDebug(__FUNCTION__, $form);
        return json_encode($form);
    }

    /**
     * Is executed when "Apply" is pressed on the configuration page and immediately after the instance has been created.
     *
     * @return void
     */
    public function ApplyChanges(): void
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
            $this->LogDebug(__FUNCTION__, $variable . ' does not exist!');
            $this->SetStatus(201);
            return;
        }

        $json = $this->ReadPropertyString('ColorVariables');
        $this->LogDebug(__FUNCTION__, $json);
        $list = json_decode($json, true);

        $count = 0;
        $profile = true;
        $name = true;
        foreach ($list as &$line) {
            if (IPS_VariableExists($line['Variable'])) {
                $this->RegisterReference($line['Variable']);
                if ($this->CheckVariable($line['Variable']) != true) {
                    $this->LogDebug(__FUNCTION__, $line['Variable'] . 'has wrong Profile!');
                    $profile = false;
                }
                if (empty($line['Name'])) {
                    $this->LogDebug(__FUNCTION__, $line['Variable'] . 'has no Name!');
                    $name = false;
                }
                $count++;
            }
            else {
                $this->LogDebug(__FUNCTION__, $line['Variable'] . ' does not exist!');
                $this->SetStatus(204);
                return;
            }
        }
        // No lights
        if ($count == 0) {
            $this->SetStatus(202);
            return;
        }
        // Wrong Profile
        if ($profile != true) {
            $this->SetStatus(203);
            return;
        }

        // Send a complete update message to the display, as parameters may have changed
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());

        $this->SetStatus(102);
    }

    /**
     * The content of the function can be overwritten in order to carry out own reactions to certain messages.
     * The function is only called for registered MessageIDs/SenderIDs combinations.
     *
     * data[0] = new value
     * data[1] = value changed?
     * data[2] = old value
     * data[3] = timestamp.
     *
     * @param int   $timestamp Continuous counter timestamp
     * @param int   $sender    Sender ID
     * @param int   $message   ID of the message
     * @param array{0:mixed,1:bool,2:mixed,3:int} $data Data of the message
     *
     * @return void
     */
    public function MessageSink(int $timestamp, int $sender, int $message, array $data): void
    {
        //$this->LogDebug(__FUNCTION__, 'SenderId: '.$sender.' Data: '.print_r($data, true), 0);
        switch ($message) {
            case VM_UPDATE:
                // ReceiverVariable
                if ($sender != $this->ReadPropertyInteger('StateVariable')) {
                    $this->LogDebug(__FUNCTION__, 'SenderID: ' . $sender . ' unknown!');
                } else {
                    if ($data[0] == true && $data[1] == true) { // OnChange on TRUE, i.e. motion detected
                        $this->LogDebug(__FUNCTION__, 'OnChange on TRUE - lights ON for ' . $sender);
                        $this->Switch(true);
                    } elseif ($data[0] == false && $data[1] == true) { // OnChange on FALSE, i.e. no motion
                        $this->LogDebug(__FUNCTION__, 'OnChange on FALSE - lights OFF for ' . $sender);
                        $this->Switch(false);
                    } else { // OnChange on FALSE, i.e. no change of status
                        $this->LogDebug(__FUNCTION__, 'OnChange unchanged - status not changed');
                    }
                }
                break;
        }
    }

    /**
     * Is called when, for example, a button is clicked in the visualization.
     *
     * @param string $ident Ident of the variable
     * @param mixed $value The value to be set
     *
     * @return void
     */
    public function RequestAction(string $ident, mixed $value): void
    {
        // Debug output
        $this->LogDebug(__FUNCTION__, $ident . ' => ' . $value);
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
                break;
            case 'autostart':
                $this->SetValueBoolean($ident, $value);
                break;
            case 'resume':
                $this->SetValueBoolean($ident, $value);
                break;
            default:
                // should only be 'color_xxxxx'
                if (str_starts_with($ident, 'color_')) {
                    $var = (int) substr($ident, 6);
                    $this->UpdateColorVariable($var, (int) $value);
                    return;
                }
                break;
        }
    }

    /**
     * If the HTML-SDK is to be used, this function must be overwritten in order to return the HTML content.
     *
     * @return string Initial display of a representation via HTML SDK
     */
    public function GetVisualizationTile(): string
    {
        // Add a script to set the values when loading, analogous to changes at runtime
        // Although the return from GetFullUpdateMessage is already JSON-encoded, json_encode is still executed a second time
        // This adds quotation marks to the string and any quotation marks within it are escaped correctly
        $initialHandling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ');</script>';
        // Add static HTML from file
        $module = file_get_contents(__DIR__ . '/module.html');
        // Important: $initialHandling at the end, as the handleMessage function is only defined in the HTML
        return $module . $initialHandling;
    }

    /**
     * Generate a message that updates all elements in the HTML display.
     *
     * @return string JSON encoded message information
     */
    private function GetFullUpdateMessage(): string
    {
        $colors = [];

        $json = $this->ReadPropertyString('ColorVariables');
        $list = json_decode($json, true);

        $count = 1;
        $name = true;
        foreach ($list as &$line) {
            if (IPS_VariableExists($line['Variable'])) {
                $colors[] = [
                    'id'    => (string) $line['Variable'],
                    'color' => $this->GetColorFormatted($line['Color']),
                    'name'  => empty($line['Name']) ? $this->Translate('Group') . ' ' . $count : $line['Name']
                ];
                $count++;
            }
        }

        $result = [
            'active'    => $this->GetValue('active'),
            'autostart' => $this->GetValue('autostart'),
            'resume'    => $this->GetValue('resume'),
            'step'      => $this->GetValue('increment'),
            'speed'     => $this->GetValue('transition'),
            'editable'  => $this->ReadPropertyBoolean('CheckColor'),
            'colors'    => $colors
        ];

        $json = json_encode($result, JSON_UNESCAPED_SLASHES);
        $this->LogDebug(__FUNCTION__, $json);

        return $json;
    }

    /**
     * Activated or deactivated the color loop functionality.
     *
     * @param bool $value False for transition otherwise true
     *
     * @return void
     */
    private function Active($value): void
    {
        $this->LogDebug(__FUNCTION__, $value);
        if ($value) { // Acvitvate
            $vid = $this->ReadPropertyInteger('StateVariable');
            if (IPS_VariableExists($vid)) {
                $value = GetValue($vid); // no modul getvalue!!!
                if ($value) {
                    $this->LogDebug(__FUNCTION__, 'Activate now!');
                    $this->Switch(true);
                } else {
                    $this->LogDebug(__FUNCTION__, 'No activation, only generally activated!');
                }
            }
        } else { // Deactivated
            $this->LogDebug(__FUNCTION__, 'Deactivate now!');
            $this->Switch(false);
        }
    }

    /**
     * Switch color loop ON or OFF.
     *
     * @param bool $value
     *
     * @return void
     */
    private function Switch($value): void
    {
        $this->LogDebug(__FUNCTION__, ($value ? 'true' : 'false'));
        $auto = $this->GetValue('autostart');
        $resume = $this->GetValue('resume');
        if ($value) { // ON
            $ison = $this->GetValue('active');
            if ($auto && !$ison) {
                $ison = true;
                $this->SetValueBoolean('active', $ison);
            }
            // only if color loop is active switched!
            if ($ison) {
                $tran = $this->GetValue('transition');
                $this->LogDebug(__FUNCTION__, 'Trans: ' . $tran);
                $json = $this->ReadPropertyString('ColorVariables');
                $list = json_decode($json, true);
                $data = [];
                foreach ($list as &$line) {
                    $varid = $line['Variable'];
                    $color = $line['Color']; // color from config
                    $ident = 'color_' . (string) $varid;
                    // color is transparent then from light
                    if ($color == -1) {
                        $color = GetValue($varid);
                    }
                    $data[] = [$varid, $color];
                }
                $this->LogDebug(__FUNCTION__, 'Data: ' . print_r($data, true));
                $buffer = $this->GetBuffer('loop_data');
                if (!$resume || empty($buffer)) {
                    $this->SetBuffer('loop_data', serialize($data));
                }
                // Start Timer
                $this->SetTimerInterval('ColorLoopTrigger', $tran * 1000);
            }
        } else { // OFF
            $this->SetTimerInterval('ColorLoopTrigger', 0);
            // continue with the last colors?
            if (!$resume) {
                $this->SetBuffer('loop_data', '');
            }
        }
    }

    /**
     * Cycle the colors of the devices.
     *
     * @return void
     */
    private function Cycle(): void
    {
        $buffer = $this->GetBuffer('loop_data');
        $last = unserialize($buffer);
        $step = $this->GetValue('increment');
        $this->LogDebug(__FUNCTION__, 'Step size: ' . $step);
        $next = [];
        foreach ($last as $index => $data) {
            $this->LogDebug(__FUNCTION__, '(1): ' . $data[1]);
            $rgb = $this->int2rgb($data[1]);
            $this->LogDebug(__FUNCTION__, '(2): ' . $rgb[0] . ', ' . $rgb[1] . ', ' . $rgb[2]);
            $hsl = $this->rgb2hsl($rgb[0], $rgb[1], $rgb[2]);
            $this->LogDebug(__FUNCTION__, '(3): ' . $hsl[0] . ', ' . $hsl[1] . ', ' . $hsl[2]);
            $hsl[0] = ($hsl[0] + $step) % 360;
            $this->LogDebug(__FUNCTION__, '(4): ' . $hsl[0] . ', ' . $hsl[1] . ', ' . $hsl[2]);
            $rgb = $this->hsl2rgb($hsl[0], $hsl[1], $hsl[2]);
            $this->LogDebug(__FUNCTION__, '(5): ' . $rgb[0] . ', ' . $rgb[1] . ', ' . $rgb[2]);
            $col = $this->rgb2int($rgb);
            $this->LogDebug(__FUNCTION__, '(6): ' . $col);
            $ret = RequestAction($data[0], $col);
            $next[] = [$data[0], $col];
        }
        //$this->LogDebug(__FUNCTION__, 'Data: ' . print_r($next, true), 0);
        $this->SetBuffer('loop_data', serialize($next));
    }

    /**
     * Checks if a variable is suitable for color cycling.
     *
     * Priority order:
     *  1. Variable of type integer
     *  2. Variable profile (custom or standard)
     *  3. Variable Presentation (VARIABLE_PRESENTATION_COLOR)
     *
     * @param int $variable  The ID of the Symcon variable to check.
     *
     * @return bool True if the variable is suitable for color cycling, false otherwise.
     */
    private function CheckVariable(int $variable): bool
    {
        $variable = IPS_GetVariable($variable);

        if (!in_array($variable['VariableType'], [VARIABLETYPE_INTEGER], true)) {
            return false;
        }

        // Variable profile
        $profile = $variable['VariableCustomProfile'] ?: $variable['VariableProfile'];
        if ($profile && ($profile === '~HexColor')) {
            return true;
        }

        // Presentation
        $presentation = $variable['VariableCustomPresentation'] ?: $variable['VariablePresentation'];
        if (!empty($presentation)) {
            if ($presentation['PRESENTATION'] == VARIABLE_PRESENTATION_COLOR) {
                return true;
            }
        }
        return false;
    }

    /**
     * Updates the colour value of an entry in ColorVariables
     * based on the variable ID and returns the property.
     *
     * @param int $variable  IPS variable ID (corresponds to ‘Variable’ in the list entry)
     * @param int $color     RGB as an integer, -1 = transparent
     */
    private function UpdateColorVariable(int $variable, int $color): void
    {
        // Load the latest list from the property
        $entries = json_decode($this->ReadPropertyString('ColorVariables'), true);

        if (!is_array($entries)) {
            $this->LogMessage('ColorVariables is not a valid JSON array!', KL_ERROR);
            return;
        }

        $updated = false;
        foreach ($entries as &$entry) {
            if ((int) $entry['Variable'] === $variable) {
                $entry['Color'] = $color;
                $updated = true;
                break;
            }
        }
        unset($entry);

        if (!$updated) {
            $this->LogDebug(__FUNCTION__, 'Variable ID: ' . $variable . ' not found in ColorVariables!');
            return;
        }

        // Property aktualisieren – löst ApplyChanges() aus
        IPS_SetProperty($this->InstanceID, 'ColorVariables', json_encode($entries));
        IPS_ApplyChanges($this->InstanceID);
    }
}