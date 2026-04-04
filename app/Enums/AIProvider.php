<?php

namespace App\Enums;

enum AIProvider: string
{
    case OPENAI = 'openai';
    case ANTHROPIC = 'anthropic';
    case GOOGLE = 'google';
    case COHERE = 'cohere';
}
