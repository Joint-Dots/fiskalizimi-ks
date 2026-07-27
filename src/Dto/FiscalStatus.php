<?php

namespace Jointdots\FiskalizimiKs\Dto;

enum FiscalStatus: string
{
    case Fiscalized = 'fiscalized';
    case Queued     = 'queued';
    case Failed     = 'failed';
    case Submitting = 'submitting';
    case Pending    = 'pending';
    case Rejected   = 'rejected';
    case Unresolved = 'unresolved';
}
