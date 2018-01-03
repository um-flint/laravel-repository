<?php

namespace UMFlint\Repository\Rules;

abstract class BaseRules
{
    /**
     * Array of rules.
     *
     * @param  null $model
     * @return array
     */
    abstract protected function rules($model = null): array;

    /**
     * Get rules.
     *
     * @author Donald Wilcox <dowilcox@umflint.edu>
     * @param null $model
     * @return array
     */
    public static function getRules($model = null): array
    {
        return (new static)->rules($model);
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