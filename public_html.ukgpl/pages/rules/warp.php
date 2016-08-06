<?php include("pages/rules/index.php"); ?>

<H3>Warp Incidents</H3>

<P>In on-line racing, each driver runs GPL as a client. The client manages the graphics, car dynamics, game controller (i.e. steering wheel) and collision engine for its driver's car. In addition the client exchanges positional data with the server. Each client tells the server where its driver is (x, y, z coordinates, speed etc) and in turn receives similar data back from the server about all the other drivers. With good connections this exchange of information is seamless but if there is a delay in transmission or loss of data at any stage a phenomenon commonly known as warp occurs. When the server doesn't provide all the data about another car, the client may decide to ignore the other car (in which case the car miraculously disappears) or it decides extrapolate and guess where the other car has gone (if it guesses wrong the other car will appear to jump about once normal data flow has been reinstated). In general, severe warp will result in cars disappearing and reappearing whereas mild warp will just make other cars appear to be moving erratically.</P>

<P>In a situation where two cars are traveling line astern under braking,if the lead car client looses some data it may extrapolate the position of the following car to compensate. This can be a problem since GPL uses the last known speed of the following car and doesn't take into account the fact that the following driver may be braking heavily. Consequently the lead car client thinks the following car is going faster than it actually is and it may decide to register a collision. Neither the following car client nor the server will register a collision since they were aware of the true position of the following car. This is an example of a warp incident under braking.</P>

<P>A similar situation occurs when two cars are traveling line astern but this time under acceleration. In this case if the following car client looses data then it may decide the leading car has stopped accelerating (it effectively extrapolates a position using the last know speed of the lead car). In which case the following car client will assume the following car closes rapidly on the lead car and a collision may be registered.  In this case the following car client will register the collision, but the server and the lead car client will not.  This is an example of a warp incident under acceleration.</P>

<P>For an incident to be classed as a warp incident there must be no evidence of the contact registered on the victim's client replay being present on the server replay.  This does not mean that there has to be clear contact on the server before a driver can be blamed for a shunt; it simply means that if there is clear contact on the server then a driver cannot claim warp as a mitigating factor and expect to get a reduced penalty.</P>

<P>Driving techniques which reduce the risk of warp are described in the GPLAC <A HREF="/rec_dvr_beh/rec_driver_beh.htm" TARGET=_top> Recommended Driver Behaviour</A>.  In essence it is advisable not to drive too close and probably a good idea for following drivers to position their car slightly left or right so that it is visible in the leading car's mirrors rather than line astern and consequently in the leading car's blind spot.</P>

<P>When assessing incidents, moderators will consider whether or not warp can be considered a mitigating factor.  Sometimes warp is entirely to blame in which case a driver will be exonerated and not penalised in any way.  But more often than not, warp simply exacerbates a situation where <b>contact would have been made anyway</b>.  In these situations a moderator may decide to reduce the penalty because warp made the incident much worst than it actually was.  When checking for warp, moderators will take into account the "collision boxes" GPL uses.  "Collision boxes" are just that, boxes that GPL uses to determine where a car is on the track in relation to the others; they are not sophisticated shapes denoting the actual outline of the cars.  Consequently it is not possible to interlock wheels in GPL and expect to get away with it, nor is it possible to gently nudge an opponent’s exhaust pipes without registering significant contact.</P>

<h4>Typical Scenarios:</h4>

<P>Warp Incident Caused by Careless Driving: Penalty 1 Place
<ol>
  <li> Prior to the warp incident, the following car was driving inches from the gearbox of the leading car for a protracted length of time rather than momentarily as would be the case as part of a genuine overtaking attempt.</li>
  <li> Prior to the warp incident, on a straight, the cars are running side by side and one car moves over from their half of the track and squeezes the other car unnecessarily.</li>
  <li> Prior to the warp incident, on a straight, the cars are running side by side and one car moves over from their half of the track and straddles the centre line. </li>
</ol>

<P>Genuine Warp Incident: No penalty - Racing Incident
<ol>
  <li> Prior to the warp incident, the following car was driving no closer than a car's length from the leading car but then closed up under braking, possibly due to the leading car braking early, but no actual contact would have been made.</li>
  <li> Prior to the warp incident, on a straight, the cars are running side by side and one car moves over from their half of the track toward the centre line but does not straddle it. No actual contact would have been made.</li>
  <li> Prior to the warp incident, in a corner, after a legitimate overtaking attempt the cars end up side by side. One driver looses control and strays from their side of the track but no actual contact is made.</li>
  <li> Prior to the warp incident, in a corner, after a legitimate overtaking attempt the cars end up side by side. Both drivers move over instead of staying on their own side of the track but no actual contact is made.</li>
</ol>