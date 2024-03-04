<?php

  include ("classes.php");

  if (isset ($_GET["logfile"]))
    $wowlogfile = $_GET["logfile"];
  else {
    $wowlogfile = "uploads/wowlog.log";
  }

  $spellarray = Array (10207,10199,25306,18809,13021,10216, 10197);
  $wowlog = trimlog (file ($wowlogfile), $spellarray);
  $namedbosses = Array ("\"Emperor Vek'lor\"", "\"Eye of C'Thun\"", "\"Princess Yauj\"", "\"Princess Yauj\"", "\"Lord Kri\"");
  $ignite = Array ();
  $igniteflag = false;
  $iscrit = false;
  $ignitearray = Array ();
  $level = 0;
  $fastforward = false;
  $encounterflag = false;
  $c45 = 0;
  $gnd = 0;
  $gdl = 0;
  $ctk = 0;

  foreach ($wowlog as $line) {
    $tstamparray = explode (" ", $line);
    $tstamp = $tstamparray[0] . " " . $tstamparray[1];
    $larraysp = explode ("  ", $line);
    $larraycm = explode (",", $larraysp[1]);

    if ($larraycm[0] == "ENCOUNTER_START") {
      if (encounter_ends ($larraycm[1], $tstamp))
        $encounterflag = true;
        $boss = strtolower ($larraycm[2]);
    }
    if ($larraycm[0] == "ENCOUNTER_END")
      $encounterflag = false;

    if (!$fastforward) {
      if ($encounterflag) {
        //print $larraycm[6] . "\n";
        if ($larraycm[0] == "SPELL_DAMAGE" && in_array ($larraycm[9], $spellarray) && $larraycm[35] == "1" && (strtolower ($larraycm[6]) == $boss || in_array ($larraycm[6], $namedbosses))) {
          if (checkforfive ($tstamp, $larraycm[12])) {
            $ignite = getignite ($larraycm, $tstamp, $spellarray);
            $ret = get_ticks ($larraycm, $tstamp);
            $ignite->totalticks = $ret[0];
            $ignite->refresh = $ret[1];
            array_push ($ignitearray, $ignite);
            $fastforward = true;
            $mobid = $larraycm[5];
          }
        }
      }
    } else {
      if ($larraycm[0] == "UNIT_DIED" && (strtolower ($larraycm[6]) == $boss || in_array ($larraycm[6], $namedbosses)))
        $fastforward = false;
      if ($larraycm[0] == "SPELL_AURA_REMOVED" && $larraycm[9] == 12654 && (strtolower ($larraycm[6]) == $boss || in_array ($larraycm[6], $namedbosses)))
        $fastforward = false;
    }
  }

  include ("header.html");
  print "<pre>\n";
  $fname = rand (1000000, 9999999) . ".txt";
  $f = fopen ("reports/$fname", "w");

  foreach ($ignitearray as $igniteobj) {
    fputs ($f, "Boss: " . $igniteobj->mob . "\n");
    fputs ($f, "Ignite Owner: " . $igniteobj->owner . "\n");
    fputs ($f, "Total Ticks: " . $igniteobj->totalticks . "\n");
    fputs ($f, "Tick Sampling: " . implode (",", $igniteobj->tick) . "\n");
    fputs ($f, "Refreshes: " . $igniteobj->refresh . "\n");
    fputs ($f, "Contributors:\n");
    foreach ($igniteobj->contributions as $contrib) {
      fputs ($f, $contrib->contributor . "\t\t" . $contrib->spell . "\t\t" . $contrib->damage . "\n");
    }
    fputs ($f, "\n");
  }

  fclose ($f);
  print "This report can be referenced <a href=https://www.mattshouse.com/ignite/reports/$fname>here</a> in the future.\n\n";
  include ("reports/" . $fname);

  print "GND = " . $GLOBALS["gnd"] . "\n";
  print "GDL = " . $GLOBALS["gdl"] . "\n";
  print "C45 = " . $GLOBALS["c45"] . "\n";
  print "CTK = " . $GLOBALS["ctk"] . "\n";

  print "</pre>\n";
  include ("footer.html");

  function timer ($starttime) {


  }

  function trimlog ($wowlog, $spellarray) {
    $trimlog = Array ();
    $headers = Array ("UNIT_DIED", "ENCOUNTER_START", "ENCOUNTER_END");

    foreach ($wowlog as $line) {
      $tstamparray = explode (" ", $line);
      if (!isset ($tstamparray[1]))
        die ("Not a valid combatlog\n");
      $tstamp = $tstamparray[0] . " " . $tstamparray[1];
      $larraysp = explode ("  ", $line);
      $larraycm = explode (",", $larraysp[1]);

      if (in_array ($larraycm[0], $headers))
        array_push ($trimlog, $line);

      switch ($larraycm[0]) {
        case "SPELL_DAMAGE":
          if (in_array ($larraycm[9], $spellarray) && $larraycm[35] == "1")
            array_push ($trimlog, $line);
        break;

        case "SPELL_AURA_APPLIED":
          if ($larraycm[9] == 12654)
            array_push ($trimlog, $line);
        break;

        case "SPELL_AURA_REMOVED":
          if ($larraycm[9] == 12654)
            array_push ($trimlog, $line);
        break;

        case "SPELL_AURA_REFRESH":
          if ($larraycm[9] == 12654)
            array_push ($trimlog, $line);
        break;

        case "SPELL_AURA_APPLIED_DOSE":
          if ($larraycm[9] == 12654)
            array_push ($trimlog, $line);
        break;
        case "SPELL_PERIODIC_DAMAGE":
          if ($larraycm[9] == 12654)
            array_push ($trimlog, $line);
        break;

      }
    }
    //print_r ($trimlog);
    //die ();
    return $trimlog;
  }

  function getignite ($larray, $tstamp, $spellarray) {
    $ignite = new Ignite;
    $ignite->contributions = Array ();;
    $level = 0;
    $ignite->mobid = $larray[5];
    $ignite->mob = $larray[6];
    $ignite->owner = $larray[2];

    while ($level != 5) {
      $ret = getdebufflevel ($ignite->mobid, $tstamp, $larray[1]);
      $level = $ret[0];
      //print "level = $level\n";
      if ($level == 5) {
        $ticks = calculateticks ($tstamp, $larray);
        $ignite->tick = $ticks;
      }

      $ignitecontrib = new IgniteContrib;
      $ignitecontrib->contributor = $larray[2];
      $ignitecontrib->spell = $larray[10];
      $ignitecontrib->damage = $larray[28];

      array_push ($ignite->contributions, $ignitecontrib);

      $tstamp = advancelog ($ret[1]);

      $ret = getnewdamagelog ($tstamp, $spellarray, $ignite->mobid);
      $larray = $ret[0];
      //$tstamp = $ret[1];
      //print "----\n";
      //print_r ($larray);
    }

    return $ignite;
  }

  function calculateticks ($tstamp, $larray) {
    $t1 = microtime (true);
    $mobid = $larray[5];
    $ticks = Array ();

    $tstampflag = false;
    foreach ($GLOBALS["wowlog"] as $line) {
      $larraysp = explode ("  ", $line);
      $larraycm = explode (",", $larraysp[1]);
      $tstamparray = explode (" ", $line);
      $newtstamp = $tstamparray[0] . " " . $tstamparray[1];

      if ($tstampflag) {
        if ($larraycm[0] == "UNIT_DIED" && $larraycm[5] == $mobid){
          $t2 = microtime (true);
          $GLOBALS["ctk"] = $GLOBALS["ctk"] + ($t2 - $t1);
          array_push ($ticks, 0);
          array_push ($ticks, 0);;
        }
        if ($larraycm[0] == "SPELL_PERIODIC_DAMAGE" && $larraycm[9] == 12654 && $larraycm[5] == $mobid) {
          $t2 = microtime (true);
          $GLOBALS["ctk"] = $GLOBALS["ctk"] + ($t2 - $t1);
          array_push ($ticks, $larraycm[28]);
        }
      }
      if ($newtstamp == $tstamp)
        $tstampflag = true;
      if (count ($ticks) == 4)
        return $ticks;
    }
    $t2 = microtime (true);
    $GLOBALS["ctk"] = $GLOBALS["ctk"] + ($t2 - $t1);
    return $ticks;
  }

  function advancelog ($tstamp) {
    $tstampflag = false;
    foreach ($GLOBALS["wowlog"] as $line) {
      $tstamparray = explode (" ", $line);
      $newtstamp = $tstamparray[0] . " " . $tstamparray[1];

      if ($tstampflag) {
        if ($tstamp != $newtstamp)
          return $newtstamp;
      }
      if ($newtstamp == $tstamp)
        $tstampflag = true;
    }
    return $tstamp;
  }

  function encounter_ends ($encid, $tstamp) {
    $tstampflag = false;
    foreach ($GLOBALS["wowlog"] as $line) {
      $tstamparray = explode (" ", $line);
      $newtstamp = $tstamparray[0] . " " . $tstamparray[1];

      if ($tstampflag) {
        $larraysp = explode ("  ", $line);
        $larraycm = explode (",", $larraysp[1]);
        if ($larraycm[0] == "ENCOUNTER_END" && $larraycm[1] == $encid) {
          return true;
        }
        if ($larraycm[0] == "ENCOUNTER_START") {
          return false;
        }
      }
      if ($newtstamp == $tstamp)
        $tstampflag = true;
    }
    return true;
  }

  function getnewdamagelog ($tstamp, $spellarray, $mobid) {
    $t1 = microtime (true);
    $tstampflag = false;
    //print "Stamp: $tstamp\n";
    foreach ($GLOBALS["wowlog"] as $line) {
      $tstamparray = explode (" ", $line);
      $newtstamp = $tstamparray[0] . " " . $tstamparray[1];
      if ($newtstamp == $tstamp)
        $tstampflag = true;

      if ($tstampflag) {
        $larraysp = explode ("  ", $line);
        $larraycm = explode (",", $larraysp[1]);
        if ($larraycm[0] == "SPELL_DAMAGE" && in_array ($larraycm[9], $spellarray) && $larraycm[5] == $mobid && $larraycm[35] == "1") {
          $t2 = microtime (true);
          $GLOBALS["gnd"] = $GLOBALS["gnd"] + ($t2 - $t1);
          return Array ($larraycm, $newtstamp);
        }
      }
      //if ($newtstamp == $tstamp)
        //$tstampflag = true;
    }
  }

  function get_ticks ($larraycm, $tstamp) {
    $tstampflag = false;
    $mobid = $larraycm[5];
    $tick = 1;
    $refresh = 1;
    foreach ($GLOBALS["wowlog"] as $line) {
      $larraysp = explode ("  ", $line);
      $larraycm = explode (",", $larraysp[1]);
      $tstamparray = explode (" ", $line);
      $newtstamp = $tstamparray[0] . " " . $tstamparray[1];

      if ($tstampflag) {
        if ($larraycm[0] == "SPELL_AURA_REMOVED" && $larraycm[9] == 12654 && $larraycm[5] == $mobid){
          return Array ($tick, $refresh);
        }
        if ($larraycm[0] == "UNIT_DIED" && $larraycm[5] == $mobid){
          return Array ($tick, $refresh);
        }
        if ($larraycm[0] == "SPELL_AURA_REFRESH" && $larraycm[9] == 12654 && $larraycm[5] == $mobid) {
          $refresh++;
        }
        if ($larraycm[0] == "SPELL_PERIODIC_DAMAGE" && $larraycm[9] == 12654 && $larraycm[5] == $mobid) {
          $tick++;
        }
      }
      if ($newtstamp == $tstamp)
        $tstampflag = true;
    }
  }

  function checkforfive ($tstamp, $mobid) {
    $t1 = microtime (true);

    $tstampflag = false;
    foreach ($GLOBALS["wowlog"] as $line) {
      $larraysp = explode ("  ", $line);
      $larraycm = explode (",", $larraysp[1]);
      $tstamparray = explode (" ", $line);
      $newtstamp = $tstamparray[0] . " " . $tstamparray[1];

      if ($tstampflag) {
        if ($larraycm[0] == "SPELL_AURA_REMOVED" && $larraycm[9] == 12654 && $larraycm[5] == $mobid){
          $t2 = microtime (true);
          $GLOBALS["c45"] = $GLOBALS["c45"] + ($t2 - $t1);
          return false;
        }
        if ($larraycm[0] == "UNIT_DIED" && $larraycm[5] == $mobid){
          $t2 = microtime (true);
          $GLOBALS["c45"] = $GLOBALS["c45"] + ($t2 - $t1);
          return false;
        }
        if ($larraycm[0] == "SPELL_AURA_APPLIED_DOSE" && $larraycm[9] == 12654 && $larraycm[12] == "DEBUFF" && $larraycm[13] == 5 && $larraycm[5] == $mobid) {
          $t2 = microtime (true);
          $GLOBALS["c45"] = $GLOBALS["c45"] + ($t2 - $t1);
          return true;
        }
      }
      if ($newtstamp == $tstamp)
        $tstampflag = true;
    }
    $t2 = microtime (true);
    $GLOBALS["c45"] = $GLOBALS["c45"] + ($t2 - $t1);
    return false;
  }

  function incstamp ($stamp) {
    $b = substr ($stamp, 0, 14);
    $e = substr ($stamp, 14);
    $e++;

    if (strlen ($e) == 1)
      $e = "00" . $e;
    elseif (strlen ($e) == 2)
      $e = "0" . $e;

    print "--$stamp--$b--$e--\n";
    return $b . $e;
  }

  function getdebufflevel ($mobid, $tstamp, $owner) {
    $t1 = microtime (true);
    $tstampflag = false;
    foreach ($GLOBALS["wowlog"] as $line) {
      $tstamparray = explode (" ", $line);
      $newtstamp = $tstamparray[0] . " " . $tstamparray[1];

      if ($tstampflag) {
        $larraysp = explode ("  ", $line);
        $larraycm = explode (",", $larraysp[1]);
        //print_r ($larraycm);
        //print "mobid = $mobid\n";
        if ($larraycm[0] == "SPELL_AURA_APPLIED" && $larraycm[9] == 12654 && trim ($larraycm[12]) == "DEBUFF" && $larraycm[5] == $mobid && $larraycm[1] == $owner) {
          $t2 = microtime (true);
          $GLOBALS["gdl"] = $GLOBALS["gdl"] + ($t2 - $t1);
          return Array (1, $newtstamp);
        }
        if ($larraycm[0] == "SPELL_AURA_APPLIED_DOSE" && $larraycm[9] == 12654 && $larraycm[12] == "DEBUFF" && $larraycm[5] == $mobid) {
          $t2 = microtime (true);
          $GLOBALS["gdl"] = $GLOBALS["gdl"] + ($t2 - $t1);
          return Array (trim ($larraycm[13]), $newtstamp);
        }
      }
      if ($newtstamp == $tstamp) {
        $tstampflag = true;
        //print "comparing $newtstamp == $tstamp\n";
      }
    }
    $t2 = microtime (true);
    $GLOBALS["gdl"] = $GLOBALS["gdl"] + ($t2 - $t1);
    print "found nothing\n";
    return false;
  }

?>

