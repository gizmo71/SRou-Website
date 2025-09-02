<?php include("pages/rules/index.php"); ?>

<H1 ALIGN="CENTER">H-Shifters</H1>

<P>The use of a H-Shifter increases the authenticity and enhances the gameplay in GPL.  However when used with GPL Shift, with or without the automatic clutch facility enabled, 
the gear shift time (i.e. the time spent in neutral during the shift) may be reduced to less than the 0.222 seconds (222 milliseconds)
programmed into the original GPL executables.  When combined with speed shifting the ability to shift in less than 222 milliseconds, yields a definite advantage.  The H-Shifter is more difficult to 
use compared to paddles or a sequential shifter so to some extent this extra difficulty negates the advantage in shift times.</P>

<P>There are two measures that can be taken to offset the advantage of using H-Shifters:
<LI>Enable the clutch in GPL Shift</LI>
<BLOCKQUOTE/>
Gear changing with a manual clutch does make changing gear more difficult and hence mitigates the advantage of H-Shifters.  In the GPLshift.ini file there is a section labelled "Clutch Device".  
Setting the "Clutch_Threshold" to anything other than zero will enable the clutch.  
It is recommended that the threshold is set to 5 which means the clutch pedal will need to be pressed to 50% of its travel in order to disengage the clutch.  This will 
ensure a realistic pedal action is required to change gear.  The "Clutch_Shift_Sound" should be set to 1 or 2 in order to generate some audio feedback for missed gears.  
A sound file can be downloaded from the ACT Labs <A HREF="http://www.act-labs.com/race_zonelegacy.htm">website</A> under the section entitled "For the Hardcore Users:".  
The sound file must be renamed ShftErr.waw and saved in the GPL root directory.
</BLOCKQUOTE>

<LI>Use <A HREF="https://bitbucket.org/NitzerEbb/fairshift/wiki/Home"> FairShift</A></LI>
<BLOCKQUOTE/>
This utility will ensure the gear shifts take at least 0.222 seconds (the original shifting time in GPL) when the clutch is not enabled. When the cluch is 
enabled, the original shifting time is not enforced but Fairshift does force the driver to lift the throttle during upshifts. If the driver does not lift, Fairshift 
registers a missed gear. Guidance on howto install and configure Fairshift is <A HREF="/files/fairshift/Installing Fairshift.pdf" TARGET="_top">here</A>.
</BLOCKQUOTE>
<BLOCKQUOTE/>
In order to use Fairshift the GPL executables must be listed in the list.xml file which is in the Fairshift installation directory (default location is C:\sierra\fairshift). 
For convenience all the GPL mods that are used at UKGPL appear in this <A HREF="/files/fairshift/list.xml" TARGET="_top">list.xml file</A>.
</BLOCKQUOTE>
</P>
