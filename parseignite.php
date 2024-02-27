<?php

  include ("classes.php");

  if (isset ($_GET["logfile"]))
    $wowlogfile = $_GET["logfile"];
  else {
    $wowlogfile = "uploads/wowlog.log";
  }

  $wowlog = trimlog (file ($wowlogfile));
  $ignite = Array ();
  $igniteflag = false;
  $iscrit = false;
  $spellarray = Array (10207,10199,25306,18809,13021,10216);
  $ignitearray = Array ();
  $level = 0;
  $fastforward = false;
  $encounterflag = false;
  $c45 = 0;
  $gnd = 0;
  $gdl = 0;

  foreach ($wowlog as $line) {
    $tstamparray = explode (" ", $line);
    $tstamp = $tstamparray[0] . " " . $tstamparray[1];
    $larraysp = substr ($line, 19);
    $larraycm = explode (",", $larraysp);

    if ($larraycm[0] == "ENCOUNTER_START")
      $encounterflag = true;
    if ($larraycm[0] == "ENCOUNTER_END")
      $encounterflag = false;

    if (!$fastforward) {
      if ($encounterflag) {
        if ($larraycm[0] == "SPELL_DAMAGE" && in_array ($larraycm[9], $spellarray) && $larraycm[35] == "1") {
          //print_r ($larraycm);
          if (checkforfive ($tstamp, $larraycm[12])) {
            $ignite = getignite ($larraycm, $tstamp, $spellarray);
            array_push ($ignitearray, $ignite);
            //print_r ($ignite);
            $fastforward = true;
            $mobid = $larraycm[5];
          }
        }
      }
    } else {
      if ($larraycm[0] == "UNIT_DIED" && $larraycm[5] == $mobid)
        $fastforward = false;
      if ($larraycm[0] == "SPELL_AURA_REMOVED" && $larraycm[9] == 12654 && $larraycm[5] == $mobid)
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
    fputs ($f, "Tick: " . $igniteobj->tick . " per 2s\n");
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

  print "</pre>\n";
  include ("footer.html");

  function timer ($starttime) {


  }

  function trimlog ($wowlog) {
    $trimlog = Array ();
    $headers = Array ("SPELL_DAMAGE", "SPELL_AURA_APPLIED", "SPELL_AURA_APPLIED_DOSE", "SPELL_AURA_REMOVED", "UNIT_DIED", "ENCOUNTER_START", "ENCOUNTER_END");

    foreach ($wowlog as $line) {
      $tstamparray = explode (" ", $line);
      $tstamp = $tstamparray[0] . " " . $tstamparray[1];
      $larraysp = substr ($line, 19);
      $larraycm = explode (",", $larraysp);

      if (in_array ($larraycm[0], $headers))
        array_push ($trimlog, $line);
    }

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
      //print "$larray[12], $tstamp, $larray[1]\n";
      $ret = getdebufflevel ($larray[12], $tstamp, $larray[1]);
      $level = $ret[0];
      //print $level . " level\n";
      //$tstamp = incstamp ($ret[1]);

      $ignitecontrib = new IgniteContrib;
      $ignitecontrib->contributor = $larray[2];
      $ignitecontrib->spell = $larray[10];
      $ignitecontrib->damage = $larray[28];

      array_push ($ignite->contributions, $ignitecontrib);

      $tstamp = advancelog ($ret[1]);
      $ret = getnewdamagelog ($tstamp, $spellarray);
      
      $larray = $ret[0];
      //print_r ($larray);
      //print $tstamp . " tstamp\n";
    }

    $ignite = calculateticks ($ignite);
    return $ignite;
  }

  function calculateticks ($ignite) {
    $total = 0;
    foreach ($ignite->contributions as $i) {
      $total = $total + $i->damage;
    }
    $ignite->tick = ($total * .4) / 2;
    return $ignite;
  }

  function advancelog ($tstamp) {
    //$wowlog = file ($GLOBALS["wowlogfile"]);
    $tstampflag = false;
    foreach ($GLOBALS["wowlog"] as $line) {
      $tstamparray = explode (" ", $line);
      $newtstamp = $tstamparray[0] . " " . $tstamparray[1];

      if ($tstampflag) {
        //print "advance comparing --$newtstamp-- to --$tstamp--\n";
        if ($tstamp != $newtstamp)
          return $newtstamp;
      }
      if ($newtstamp == $tstamp)
        $tstampflag = true;
    }
    return $tstamp;
  }

  function getnewdamagelog ($tstamp, $spellarray) {
    $t1 = microtime (true);
    //$wowlog = file ($GLOBALS["wowlogfile"]);
    $tstampflag = false;
    foreach ($GLOBALS["wowlog"] as $line) {
      $tstamparray = explode (" ", $line);
      $newtstamp = $tstamparray[0] . " " . $tstamparray[1];

      if ($tstampflag) {
        //print "gnd comparing --$newtstamp-- to --$tstamp--\n";
        $larraysp = substr ($line, 19);
        $larraycm = explode (",", $larraysp);
        if ($larraycm[0] == "SPELL_DAMAGE" && in_array ($larraycm[9], $spellarray) && $larraycm[35] == "1") {
          $t2 = microtime (true);
          $GLOBALS["gnd"] = $GLOBALS["gnd"] + ($t2 - $t1);
          return Array ($larraycm, $newtstamp);
        }
      }
      if ($newtstamp == $tstamp)
        $tstampflag = true;
    }
  }

  function checkforfive ($tstamp, $mobid) {
    $t1 = microtime (true);
    //$wowlog = file ($GLOBALS["wowlogfile"]);

    $tstampflag = false;
    foreach ($GLOBALS["wowlog"] as $line) {
      $larraysp = substr ($line, 19);
      $larraycm = explode (",", $larraysp);
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
    //$wowlog = file ($GLOBALS["wowlogfile"]);
    $tstampflag = false;
    foreach ($GLOBALS["wowlog"] as $line) {
      $tstamparray = explode (" ", $line);
      $newtstamp = $tstamparray[0] . " " . $tstamparray[1];
      //print "comparing --$newtstamp-- to --$tstamp--\n";

      if ($tstampflag) {
        //print "dbl comparing --$newtstamp-- to --$tstamp--\n";
        $larraysp = substr ($line, 19);
        $larraycm = explode (",", $larraysp);
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
      if ($newtstamp == $tstamp)
        $tstampflag = true;
    }
    $t2 = microtime (true);
    $GLOBALS["gdl"] = $GLOBALS["gdl"] + ($t2 - $t1);
    return false;
  }

?>

