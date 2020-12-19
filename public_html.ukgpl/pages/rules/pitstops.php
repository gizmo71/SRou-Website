<?php include("pages/rules/index.php"); ?>

<H1 ALIGN="CENTER">Pit Stops</H1>

<H2>Refuelling Stops</H2>

<P>The Pit Stop Patch written by <A HREF="http://gplmotorworks.gplworld.de/">Lee Bowden</A> can be used to refuel the car during the race. There are several versions of the patch. The Divisional Moderator will specify the version to be used at the start of the season.</P>

<H3>Pit Stop Patch version 1 - Refuelling Only</H3>

<P>Detailed instructions on how to use the patch are <A HREF="http://srmz.net/index.php?showtopic=10007">here</A>. However, the essential steps involved in a stop to refuel are:
<UL>
<LI>The driver should enter the pits carefully and slowly approach their pit stall.</LI>
<LI>The car should be brought to a stop exactly at the point where the pit board disappears.</LI>
<LI>Pressing the "space" bar starts the refuelling.</LI>
<LI>The accelerator is disabled during refuelling and the pit board reappears.</LI>
<LI>The pit board disappears when the refuelling is complete and the accelerator is re-enabled.</LI>
</UL>
</P>

<P>The original Pit Stop Patch (v1.0) can be downloaded from <A HREF="http://srmz.net/index.php?showtopic=10007">here</A>.</P>

<H3 id="pitstoppatchv2">Pit Stop Patch version 2 - Refuelling and Tyre Wear</H3>

<P>The Pit Stop patch (v2.0) incorporates tyre wear which is commensurate with the Sports Cars Extra mod. The tyre wear has been reduced in the UKGPL custom patch (v2.02) and it is tailored to our league races. In both versions, the tyre wear varies depending on the type of race. In “Grand Prix” mode, the tyre wear is less than it is in a “Long” race. The tyre wear in the UKGPL custom patch (v2.02) is such that:
<UL>
<LI>In a 67F1 90min GP race, provided they are not abused, the tyres will just last the race distance.</LI>
<LI>In a 67F1 50min Long race, the tyres will last about 60% race distance. So one pit stop for tyres is required (Trying to drive 40% of the race distance on worn tyres is not recommended).</LI>
</UL>
</P>

<P>Detailed instructions on how to use the patch are <A HREF="http://srmz.net/index.php?showtopic=13367&hl=%2Bpit+%2Bstop+%2Bpatch">here</A>. However, the essential steps involved in a stop to refuel and replenish the tyres are:
<UL>
<LI>The driver should enter the pits carefully and slowly approach their pit stall.</LI>
<LI>The car should be brought to a stop exactly at the point where the pit board disappears.</LI>
<LI>Pressing the "P" key initiates the refuelling.</LI>
<LI>Pressing the "T" key initiates the tyre change.</LI>
<LI>Refuelling and tyre changing is carried out sequentially not concurrently.</LI>
<LI>The accelerator is disabled during refuelling and tyre changing and the pit board reappears.</LI>
<LI>The pit board disappears when the refuelling and tyre changing is complete and the accelerator is re-enabled.</LI>
</UL>
</P>

<P>The UKGPL Custom patch (v2.02) can be downloaded from <A HREF="/files/77C Pit Stop v2.02.zip">here</A>. The standard version 2 patch (v2.0) can be downloaded from <A HREF="http://srmz.net/index.php?showtopic=13367&hl=%2Bpit+%2Bstop+%2Bpatch">here</A>.</P>

<HR WIDTH="50%">

<H2>Handicap Pit Stops</H2>

<P>Handicap Pit Stops are used to implement in-race time delays by manually setting the time it takes to refuel the car. The UKGPL custom Pit Stop Patch (v2.02) must be used.</p>

<P>The Divisional Moderator may decide to implement a handicap scheme whereby drivers with faster cars are required to take longer fuel stops. Alternatively, faster drivers may be required to take longer fuel stops.</P>

<P>The stop times for each chassis and/or driver will be confirmed by the divisional moderator before each race. The UKGPL Custom Pit Stop Patch (i.e. v2.02) should be configured to the correct handicap time before the race.</P>

<P>The handicap time must be set using the "Pitstop Handicap Manager" before starting GEM+. The steps to be taken for a 67F1 race are:</P>
<UL>
<LI>Delete the "gplc67.exe" from the GPL installation directory (normally "C:\Sierra\GPL").</LI>
<LI>Open the "Pitstop Handicap Manager" (i.e. run the "Pitstop Handicap Manager.exe").</LI>
<LI>Use the slider to set the required handicap time.</LI>
<LI>Press the "Save to Patch" button.</LI>
<LI>Start GEM+ to rebuild the executable with the required handicap time.</LI>
<LI>Ensure both options "77C Pit Stop v2.02" and "78X KeyPress v.08" are enabled in GEM+.</LI>
<LI>Although not essential, it is advisable to check the pit stop time by performing a fuel stop off-line before starting a league race.</LI>
</UL>
</P>

<P>Performing a Handicap Pit Stop involves exactly the same steps that are taken to perform a refuelling stop using the <A HREF="#pitstoppatchv2">Pit Stop Patch v2.</A></P>

<P>The UKGPL Custom patch (v2.02) can be downloaded from <A HREF="/files/77C Pit Stop v2.02.zip">here</A>. 

<HR WIDTH="50%">

<H2 id="stopAndGo">Stop &amp; Go</H2>

<P>Stop &amp; Gos are used to implement in-race penalties. The Pit Stop Patch is not used.</p>

<P> The Divisional Moderator may decide to impose an in-race penalty for an infringement. Typically, a driver may be required to perform a "Stop and Go" in the Pit Stalls (not necessarily in the driver's actual stall) for taking a reset (i.e. a Shift-R).</P>

<P>The steps involved in making a Stop &amp; Go are:</P>

<UL>
<LI>On entering the pits, the driver should stop in the pit stall area,
not on the entry/exit lane. Normally the pit stall area is marked by lines
but at some circuits there are several sets of lines (Mexico) whereas at
others there maybe none other than the pit lane marking itself (Albi).
In general cars must stop within half a car's width of the stalls regardless
of the presence of stall markings. The moderator may decide to penalize
a driver who gains an unfair advantage by not stopping in the appropriate
area.</LI>
<LI>Drivers are not required to stop at their own pit stall, but they should try to 
stop within the stall area and in a position which does not hinder
other drivers who might be trying to enter or exit the pits.</LI>
<LI>If another driver is already in the pits, the pitting driver
should (if possible) stop <I>behind</I> them.</LI>
<LI>On exiting the pits, drivers should (where practical) stay off the racing line until back up to racing speed.</LI>
</UL>

