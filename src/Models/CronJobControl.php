<?php

namespace App\Models;

class CronJobControl
{
  public $id;
  public $cron_name;
  public $cron_desc;
  public $status;
  public $last_change;
  public $changed_by;
}
