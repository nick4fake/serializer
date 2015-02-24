<?php

/*
 * Copyright 2013 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JMS\Serializer\Exclusion;

use JMS\Serializer\DeserializationContext;
use JMS\Serializer\Metadata\ClassMetadata;
use JMS\Serializer\Metadata\PropertyMetadata;
use JMS\Serializer\Context;
use JMS\Serializer\SerializationContext;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Patched by Bogdan Yurov <bogdan@yurov.me>
 *
 * Added support for "+" in groups (multiple groups at time could be checked).
 *
 *
 */
class GroupsExclusionStrategy implements ExclusionStrategyInterface
{
    const DEFAULT_GROUP = 'Default';

    private $groups = [];

    public function __construct(array $groups)
    {
        $this->setGroups($groups);

        $this->language = new ExpressionLanguage();
        $this->language->addFunction(new ExpressionFunction('g', function ($arg) {
            return sprintf('g(%s)', $arg);
        }, function () {
            $val = func_get_args();
            $variables = array_shift($val);
            foreach ($val as $k) {
                if (!isset($variables['groups'][$k])) {
                    return false;
                }
            }
            return true;
        }));
    }

    public function setGroups(array $groups)
    {
        if (empty($groups)) {
            $groups = [self::DEFAULT_GROUP];
        }

        foreach ($groups as $group) {
            $this->groups[$group] = true;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function shouldSkipClass(ClassMetadata $metadata, Context $navigatorContext)
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function shouldSkipProperty(PropertyMetadata $property, Context $navigatorContext)
    {
        if (!$property->groups) {
            return !isset($this->groups[self::DEFAULT_GROUP]);
        }

        foreach ($property->groups as $group) {
            if ($group[0] === '=') {
                return !$this->language->evaluate(substr($group, 1), [
                    'groups' => $this->groups,
                    'write'  => $navigatorContext instanceof DeserializationContext,
                    'read'   => $navigatorContext instanceof SerializationContext,
                ]);
            }

            if (isset($this->groups[$group])) {
                return false;
            }
        }

        return true;
    }
}
