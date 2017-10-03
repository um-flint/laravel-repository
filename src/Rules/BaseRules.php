<?php

namespace UMFlint\Repository\Rules;

abstract class BaseRules
{
    /**
     * Array of rules.
     *
     * @return array
     */
    abstract protected function rules(): array;

    /**
     * Get rules.
     *
     * @author Donald Wilcox <dowilcox@umflint.edu>
     * @return array
     */
    public static function getRules(): array
    {
        return (new static)->rules();
    }

    /**
     * Custom validation messages.
     *
     * @return array
     */
    protected function messages()
    {
        return [];
    }

    /**
     * Get messages.
     *
     * @author Donald Wilcox <dowilcox@umflint.edu>
     * @return array
     */
    public static function getMessages(): array
    {
        return (new static)->messages();
    }
}