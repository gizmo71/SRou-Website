<?php include("pages/rules/index.php"); ?>

<H1 ALIGN="CENTER">H-Shifters</H1>

<P>The use of a H-Shifter increases the authenticity and enhances the gameplay in GPL.  However when used with the automatic clutch facility enabled, 
informal tests have been carried out that suggests the gear shift time (i.e. the time spent in neutral during the shift) may be reduced to less than the 0.222 milliseconds 
programmed into the original GPL executables.  Whether or not this yields a definite advantage has yet to be proven beyond all doubt.  The H-Shifter is more difficult to 
use compared to paddles or a sequential shifter so this may counteract any apparent advantage in shift times.  However, the UKGPL Moderating 
Team will monitor the use of H-Shifters and if the general consensus is that the use of the automatic clutch does indeed yield an unfair advantage, rules governing 
it's use will be introduced.</P>

<P>In the interim there are two measures that can be taken to offset any apparent advantage in the use of H-Shifters:
<LI>Enable the clutch</LI>
<BLOCKQUOTE/>
In the GPLshift.ini file there is a section labelled "Clutch Device".  Setting the "Clutch_Threshold" to anything other than zero will enable the clutch.  
It is recommended that the threshold is set to 5 which means the clutch pedal will need to be pressed to 50% of its travel in order to disengage the clutch.  This will 
ensure a realistic pedal action is required to change gear.  The "Clutch_Shift_Sound" should be set to 1 or 2 in order to generate some audio feedback for missed gears.  
A sound file can be downloaded from the ACT Labs <A HREF="http://www.act-labs.com/race_zonelegacy.htm">website</A> under the section entitled "For the Hardcore Users:".  
The sound file must be renamed ShftErr.waw and saved in the GPL root directory.
</BLOCKQUOTE>

<LI>Use <A HREF="https://bitbucket.org/NitzerEbb/fairshift/wiki/Home"> FairShift</A></LI>
<BLOCKQUOTE/>
This utility will ensure the gear shifts take at least 0.222 milliseconds (the original shifting time in GPL) regardless of whether or not the clutch is used.
</BLOCKQUOTE>
</P>
