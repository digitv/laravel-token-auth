<?php

namespace DigitSoft\LaravelTokenAuth\Traits;

/**
 * Trait TracksPropertiesChanges
 *
 * @package DigitSoft\LaravelTokenAuth\Traits
 */
trait TracksPropertiesChanges
{
    /**
     * Properties list with values
     *
     * @var array
     */
    protected $_state = [];

    /**
     * Check that object properties was changed after last saved state.
     *
     * @return bool
     */
    public function isChanged()
    {
        $changed = $this->getChangedProperties();

        return ! empty($changed);
    }

    /**
     * Get changed properties array.
     *
     * @return array
     */
    public function getChangedProperties()
    {
        if (empty($this->_state)) {
            return $this->grabPropertiesState();
        }

        $changed = [];
        foreach ($this->_state as $property => $value) {
            if ($this->{$property} === $value) {
                continue;
            }
            $changed[$property] = $this->{$property};
        }

        return $changed;
    }

    /**
     * Remember objects state.
     */
    public function rememberState()
    {
        $this->_state = $this->grabPropertiesState();
    }

    /**
     * Restore properties to saved state.
     *
     * @return bool
     */
    public function restoreState()
    {
        if (empty($this->_state)) {
            return false;
        }

        foreach ($this->_state as $property => $value) {
            $this->{$property} = $value;
        }

        return true;
    }

    /**
     * Grab properties state.
     *
     * @return array
     * @throws null
     */
    private function grabPropertiesState()
    {
        /** @var \ReflectionClass $ref */
        $ref = $this->getRef();
        $state = [];
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            if ($property->isStatic()) {
                // @codeCoverageIgnoreStart
                continue;
                // @codeCoverageIgnoreEnd
            }
            $state[$name] = $this->{$name};
        }

        return $state;
    }
}
