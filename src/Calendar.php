<?php

namespace Acaronlex\LaravelCalendar;

use ArrayAccess;
use DateTime;
use Illuminate\View\Factory;
use Illuminate\Support\Str;
use View;

class Calendar
{
    /**
     * @var Factory
     */
    protected $view;

    /**
     * @var EventCollection
     */
    protected $eventCollection;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var bool
     */
    protected $es6 = false;

    /**
     * Default options array
     *
     * @var array
     */
    protected $defaultOptions = [
        'initialView' => 'dayGridMonth',
        'height' => 'auto',
        'headerToolbar' => [
            'left' => 'prev,next today',
            'center' => 'title',
            'right' => 'dayGridMonth,dayGridWeek,listWeek',
        ],
        'dayMaxEventRows' => true,
    ];

    /**
     * User defined options
     *
     * @var array
     */
    protected $userOptions = [];

    /**
     * User defined callback options
     *
     * @var array
     */
    protected $callbacks = [];

    /**
     * @param Factory         $view
     * @param EventCollection $eventCollection
     */
    public function __construct()
    {
        $this->eventCollection = new EventCollection();
    }

    /**
     * Create an event DTO to add to a calendar
     *
     * @param string          $title
     * @param string          $isAllDay
     * @param string|DateTime $start If string, must be valid datetime format: http://bit.ly/1z7QWbg
     * @param string|DateTime $end   If string, must be valid datetime format: http://bit.ly/1z7QWbg
     * @param string          $id    event Id
     * @param array           $options
     * @return SimpleEvent
     */
    public static function event($title, $isAllDay, $start, $end, $id = null, $options = [])
    {
        return new SimpleEvent($title, $isAllDay, $start, $end, $id, $options);
    }

    /**
     * Create the <div> the calendar will be rendered into
     *
     * @return string
     */
    public function calendar()
    {
        return '<div id="calendar-' . $this->getId() . '"></div>';
    }

    /**
     * Get the <script> block to render the calendar (as a View)
     *
     * @return \Illuminate\View\View
     */
    public function script()
    {
        $options = $this->getOptionsJson();

        return view($this->getEs6() ? 'laravel-calendar::script-es6' : 'laravel-calendar::script', [
            'id' => $this->getId(),
            'options' => $options,
        ]);
    }

    /**
     * Customize the ID of the generated <div>
     *
     * @param string $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the ID of the generated <div>
     * This value is randomized unless a custom value was set via setId
     *
     * @return string
     */
    public function getId()
    {
        if (! empty($this->id)) {
            return $this->id;
        }

        $this->id = Str::random(8);

        return $this->id;
    }

    /**
     * change setting to output script in ES6 compatible format
     *
     * @param bool $value
     * @return $this
     */
    public function setEs6(bool $value=true)
    {
        $this->es6 = $value;

        return $this;
    }

    /**
     * Get the current ES6 value
     *
     * @return bool
     */
    public function getEs6()
    {
        return $this->es6;
    }

    /**
     * Add an event
     *
     * @param Event $event
     * @param array $customAttributes
     * @return $this
     */
    public function addEvent(Event $event, array $customAttributes = [])
    {
        $this->eventCollection->push($event, $customAttributes);

        return $this;
    }

    /**
     * Add multiple events
     *
     * @param array|ArrayAccess $events
     * @param array $customAttributes
     * @return $this
     */
    public function addEvents($events, array $customAttributes = [])
    {
        foreach ($events as $event) {
            $this->eventCollection->push($event, $customAttributes);
        }

        return $this;
    }

    /**
     * Set calendar options
     *
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options)
    {
        $this->userOptions = $options;

        return $this;
    }

    /**
     * Get the calendar options (not including the events list)
     *
     * @return array
     */
    public function getOptions()
    {
        return array_merge($this->defaultOptions, $this->userOptions);
    }

    /**
     * Set calendar callback options
     *
     * @param array $callbacks
     * @return $this
     */
    public function setCallbacks(array $callbacks)
    {
        $this->callbacks = $callbacks;

        return $this;
    }

    /**
     * Get the callbacks currently defined
     *
     * @return array
     */
    public function getCallbacks()
    {
        return $this->callbacks;
    }

    /**
     * Get options+events JSON
     *
     * @return string
     */
    public function getOptionsJson()
    {
        $options      = $this->getOptions();
        $placeholders = $this->getCallbackPlaceholders();
        $parameters   = array_merge($options, $placeholders);

        // Allow the user to override the events list with a url
        if (!isset($parameters['events'])) {
            $parameters['events'] = $this->eventCollection->toArray();
        }

        $json = $this->replaceKeys(json_encode($parameters, JSON_PRETTY_PRINT));

        if ($placeholders) {
            return $this->replaceCallbackPlaceholders($json, $placeholders);
        }

        return $json;
    }

    /**
     * Generate placeholders for callbacks, will be replaced after JSON encoding
     *
     * @return array
     */
    protected function getCallbackPlaceholders()
    {
        $callbacks    = $this->getCallbacks();
        $placeholders = [];

        foreach ($callbacks as $name => $callback) {
            $placeholders[$name] = '[' . md5($callback) . ']';
        }

        return $placeholders;
    }

    /**
     * Replace placeholders with non-JSON encoded values
     *
     * @param $json
     * @param $placeholders
     * @return string
     */
    protected function replaceCallbackPlaceholders($json, $placeholders)
    {
        $search  = [];
        $replace = [];

        foreach ($placeholders as $name => $placeholder) {
            $search[]  = '"' . $placeholder . '"';
            $replace[] = $this->getCallbacks()[$name];
        }

        return str_replace($search, $replace, $json);
    }

    /**
     * Replace keys with non-JSON encoded values
     *
     * @param $json
     * @return string
     */
    protected function replaceKeys($json)
    {
        $search  = [];
        $replace = [];

        foreach (json_decode($json) as $key => $value) {
            // Stripping double quotes from plugins values
            if(strtolower($key) === 'plugins' || strtolower($key) === 'locales'){
                if(is_array($value)) {
                    foreach ($value as $key) {
                        $search[] = '"' . $key . '"';
                        $replace[] = $key;
                    }
                }
                else{
                    $search[] = '"' . $value . '"';
                    $replace[] = $value;
                }
            }

            // Stripping double quotes from custom button callback option
            if(strtolower($key) === 'custombuttons'){
                foreach ($value as $key => $value) { // buttons
                    foreach ($value as $key => $value) { // buttons options
                        if(strtolower($key) === 'click'){
                            $search[]  = json_encode($value);
                            $replace[] = '' . $value . '';
                        }
                    }
                }
            }
        }
        $json = str_replace($search, $replace, $json);

        return preg_replace('/"(.+)":/i', '${1}:', $json);

    }
}
