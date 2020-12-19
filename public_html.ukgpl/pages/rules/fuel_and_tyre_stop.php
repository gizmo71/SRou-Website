<?php include("pages/rules/index.php"); ?>

<style>
.aligncenter {
    text-align: center;
}
</style>



<H1>Pit Stop for Fuel and Tyres</H1>

<p>This example shows a driver setting a handicap time of 18 seconds, then making a pitstop for fuel and tyres. The choice of whether or not to change tyres or to refuel, and in what order, is entirely at the discretion of the driver. However, please note, only press the keys once. If a driver changes tyres then refuels and, while refuelling is in progress, presses the &quot;T&quot; key again. The tyres will be changed again immediately after the refuel.</p>

<H2>Set up the Handicap Pitstop Time</H2>

<H3>Delete the gplc67 Executable</H3>
 
<blockquote>
  <blockquote>
    <blockquote>
      <p class="aligncenter"><img border="0" src="/images/pitStops/DeleteExe.jpg" width="800" height="282"></p> 
    </blockquote>
  </blockquote>
</blockquote>

<H3>Run the Handicap Pitstop Manager</H3>
 
<blockquote>
  <blockquote>
    <blockquote>
      <p class="aligncenter"><img border="0" src="/images/pitStops/PitstopHcapMngr.jpg" width="800" height="218"></p> 
    </blockquote>
  </blockquote>
</blockquote>

<H3>Set the Handicap Time</H3>

<p>Use the slider to adjust the handicap time. When the desired time is displayed, press the &quot;Save to Patch&quot; button to set the handicap time.</p>  

<blockquote>
  <blockquote>
    <blockquote>
      <p class="aligncenter"><img border="0" src="/images/pitStops/AdjustSlider.jpg" width="500" height="290"></p> 
    </blockquote>
  </blockquote>
</blockquote>

<H3>Confirm the Changes</H3> 

<blockquote>
  <blockquote>
    <blockquote>
      <p class="aligncenter"><img border="0" src="/images/pitStops/Confirmation.jpg" width="500" height="287"></p> 
    </blockquote>
  </blockquote>
</blockquote>

<H3>Error Message</H3> 

<p>If this error message appears, the path to the GEM+ Options folder is incorrect and the handicap time will not be set correctly. If this happens, delete the &quot;Pitstop Handicap Settings.ini&quot; file and run the Handicap Pitstop Manager again. The user will then be prompted to input the path to the GEM+ installation folder (the default is C:\GPLSecrets). The Handicap Pitstop Manager will then be able to update the &quot;77C Pit Stop v2.02.xml&quot; patch in the &quot;C:\GPLSecrets\GEM+\Options&quot; folder.</p> 

<blockquote>
  <blockquote>
    <blockquote>
      <p class="aligncenter"><img border="0" src="/images/pitStops/IniError.jpg" width="500" height="289"></p> 
    </blockquote>
  </blockquote>
</blockquote>


<H3>Close the Pitstop Handicap Manager</H3> 

<p>Use the normal windows close button to close the Pitstop Handicap Manager.</p> 

<blockquote>
  <blockquote>
    <blockquote>
    <p class="aligncenter"><img border="0" src="/images/pitStops/CloseManager.jpg" width="500" height="290"></p> 
    </blockquote>
  </blockquote>
</blockquote>

<br>
<HR WIDTH="75%">
<br>

<H2>Performing a Fuel and Tyre Pit Stop in the Race</H2>

<H3>Select the Correct Options in GEM+</H3>

<p>Start GEM+ and make sure the options &quot;77C Pit Stop v2.02&quot; and &quot;78X KeyPress v.08&quot; are checked. On starting the race, GEM+ should rebuild the executable with the correct handicap time.</p> 

<blockquote>
  <blockquote>
    <blockquote>
    <p class="aligncenter"><img border="0" src="/images/pitStops/GEM_Options.jpg" width="750" height="467"></p> 
    </blockquote>
  </blockquote>
</blockquote>

<H3>Slow down and look for the Pit Stall Board</H3>

<p>In this case the pit lane is empty so it is easy for the driver to see the Pit Board and position the car so that it is parallel to the pit wall. This may not be very easy if there is a car in the stall immediately behind the driver&apos;s stall. If access to the stall is hampered, care must be taken and if necessary, abort the stop and try again on the next lap.</p> 

<blockquote>
  <blockquote>
    <blockquote>
    <p class="aligncenter"><img border="0" src="/images/pitStops/ApproachPitStall.jpg" width="1000" height="440"></p> 
    </blockquote>
  </blockquote>
</blockquote>

<H3>Carefully approach the pit stall</H3>

<p>The driver should slow down and be prepared to stop as soon as the &quot;Pit Here&quot; Board disappears. Do not stop before the Pit Board disappears, there is a risk the accelerator will be disabled before the driver is properly in the pit stall.</p> 

<blockquote>
  <blockquote>
    <blockquote>
    <p class="aligncenter"><img border="0" src="/images/pitStops/AtPitHereSign.jpg" width="1000" height="440"></p> 
    </blockquote>
  </blockquote>
</blockquote>

<H3>Do not overshoot the pit stall</H3>

<p>Care must be taken not to overshoot the pit stall. If refuelling or a tyre change cannot be initiated you will probably have overshot the pit stall.</p> 

<blockquote>
  <blockquote>
    <blockquote>
    <p class="aligncenter"><img border="0" src="/images/pitStops/StopOverAccEnabled.jpg" width="1000" height="440"></p> 
    </blockquote>
  </blockquote>
</blockquote>

<H3>Start the Refuel</H3>

<p>Press the &quot;P&quot; key to start the refuelling. The &quot;Fueling&quot; board will flash and the accelerator will be disabled.</p> 

<blockquote>
  <blockquote>
    <blockquote>
    <p class="aligncenter"><img border="0" src="/images/pitStops/Refuelling.jpg" width="1000" height="440"></p> 
    </blockquote>
  </blockquote>
</blockquote>

<H3>Change the Tyres</H3>

<p>Press the &quot;T&quot; key to change the tyres. The &quot;Tire Change&quot; board will flash and the accelerator will be disabled. The driver can press the &quot;T&quot; key during refuelling and the tyre change will start as soon as the refuelling is completed (in this case after 18 seconds). The tyre change will take an additional 15 seconds.</p> 

<blockquote>
  <blockquote>
    <blockquote>
    <p class="aligncenter"><img border="0" src="/images/pitStops/ChangingTyres.jpg" width="1000" height="440"></p> 
    </blockquote>
  </blockquote>
</blockquote>

<H3>Carefully Move Off</H3>

<p>When the tyre change is over, the &quot;Tire Change&quot; board will disappear and the accelerator will be enabled. Carefully accelerate away, there may be other drivers entering or leaving the pit stalls.</p> 

<blockquote>
  <blockquote>
    <blockquote>
    <p class="aligncenter"><img border="0" src="/images/pitStops/MoveOff.jpg" width="1000" height="440"></p> 
    </blockquote>
  </blockquote>
</blockquote>

<br>
<HR WIDTH="75%">
<br>



