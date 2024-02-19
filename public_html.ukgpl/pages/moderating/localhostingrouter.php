<?php include("pages/moderating/index.php"); ?>

<STYLE TYPE="text/css">
.indented
   {
   padding-left: 50pt;
   padding-right: 50pt;
   }
</STYLE>

<H1 ALIGN="CENTER">Configuring a Router for Local Hosting using IGOR</H1>

<P>The ports that iGOR and GPL use need to be opened on the router and forwarded to the server hosting GPL and iGOR.</P>

<H2>GPL Ports</H2>
<P>GPL uses these ports:
<DIV CLASS="indented">
<table style='border: 1px solid #696969; border-collapse: collapse;'>
  <col style='border: 1px solid #696969; border-collapse: collapse;'> 
  <col style='border: 1px solid #696969; border-collapse: collapse;'> 
  <col style='border: 1px solid #696969; border-collapse: collapse;'> 
  <col style='border: 1px solid #696969; border-collapse: collapse;'> 
  <tr>
    <th style='border: 1px solid #696969; border-collapse: collapse;'>Port</th>
    <th style='border: 1px solid #696969; border-collapse: collapse;'>Protocol</th>
    <th style='border: 1px solid #696969; border-collapse: collapse;'>Direction</th>
    <th style='border: 1px solid #696969; border-collapse: collapse;'>Description</th>
  </tr>
  <tr>
    <td align=center>6970</td>
    <td align=center>UDP</td>
    <td align=center>Out</td>
    <td>Used by GPL to broadcast the status of the race.</td>
  </tr>
  <tr>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>6971</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>UDP</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>In</td>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>Used for ping responses.</td>
  </tr>
  <tr>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>32766-32786</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>UDP</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>In</td>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>Used for racing connections. These can be changed but they must be a contiguous block of 21 ports.</td>
  </tr>
</table>
</DIV>
</P>

<H2>iGOR Ports</H2>
<P>iGOR uses these ports:
<DIV CLASS="indented">
<table style='border: 1px solid #696969; border-collapse: collapse;'>
  <col style='border: 1px solid #696969; border-collapse: collapse;'> 
  <col style='border: 1px solid #696969; border-collapse: collapse;'> 
  <col style='border: 1px solid #696969; border-collapse: collapse;'> 
  <col style='border: 1px solid #696969; border-collapse: collapse;'> 
  <tr>
    <th style='border: 1px solid #696969; border-collapse: collapse;'>Port</th>
    <th style='border: 1px solid #696969; border-collapse: collapse;'>Protocol</th>
    <th style='border: 1px solid #696969; border-collapse: collapse;'>Direction</th>
    <th style='border: 1px solid #696969; border-collapse: collapse;'>Description</th>
  </tr>
  <tr>
    <td align=center>113</td>
    <td align=center>TCP</td>
    <td align=center>In</td>
    <td>IRC Ident Server. Not essential, but chat connections will be much quicker if this port is open.</td>
  </tr>
  <tr>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>6667</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>TCP</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>Out</td>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>IRC Chat (cannot be changed).</td>
  </tr>
  <tr>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30196</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>TCP</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>In/Out</td>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>Remote Hosting Client. This is the default but it can be changed.</td>
  </tr>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30197</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>TCP</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>Out</td>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>Race List (cannot be changed).</td>
  </tr>
  <tr>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30198</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>TCP</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>In/Out</td>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'></td>
  </tr>
  <tr>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30199</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>UDP</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>Out</td>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>Used by iGOR to broadcast the status of the race. This overrides the use of Port 6970 by GPL.</td>
  </tr>
</table>
</DIV>
</P>

<H2>Router Settings</H2>
<P>This is an example of the port forwarding rules when running two servers behind a single router. UKGPL04 uses the standard ports and UKGPL05 uses an alternative set of ports (when running a single server behind a router just use the standard ports i.e. the UKGPL04 setttings). For domestic routers all the <b>outbound ports</b> are <b>normally open</b>. Hence the table below only lists the <b>inbound ports</b>.
<DIV CLASS="indented">
<table style='border: 1px solid #696969; border-collapse: collapse;'>
  <col style='border: 1px solid #696969; border-collapse: collapse;'> 
  <col style='border: 1px solid #696969; border-collapse: collapse;'> 
  <col colspan="2" style='border: 1px solid #696969; border-collapse: collapse;'> 
  <col colspan="2" style='border: 1px solid #696969; border-collapse: collapse;'> 
  <col style='border: 1px solid #696969; border-collapse: collapse;'> 
  <col style='border: 1px solid #696969; border-collapse: collapse;'> 
  <col style='border: 1px solid #696969; border-collapse: collapse;'> 
  <col style='border: 1px solid #696969; border-collapse: collapse;'> 
  <tr>
    <th>Server</th>
    <th>Local IP Address (LAN)</th>
    <th colspan="2">External Ports</th>
    <th colspan="2">Internal Ports</th>
    <th>Protocol</th>
    <th>Description</th>
  </tr>
  <tr>
    <th></th>
    <th></th>
    <th align=center style='border: 1px solid #696969; border-collapse: collapse;'>Start</th>
    <th align=center style='border: 1px solid #696969; border-collapse: collapse;'>End</th>
    <th align=center style='border: 1px solid #696969; border-collapse: collapse;'>Start</th>
    <th align=center style='border: 1px solid #696969; border-collapse: collapse;'>End</th>
    <th></th>
    <th></th>
  </tr>
  <tr>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>UKGPL04</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>192.168.xxx.yyy</td>
    <td align=center>6971</td>
    <td align=center>6971</td>
    <td align=center>6971</td>
    <td align=center>6971</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>UDP</td>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>GPL Ping for UKGPL04.</td>
  </tr>
  <tr>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>UKGPL04</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>192.168.xxx.yyy</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30196</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30196</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30196</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30196</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>TCP</td>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>iGOR remote hosting for UKGPL04 (this is the default port).</td>
  </tr>
  <tr>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>UKGPL04</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>192.168.xxx.yyy</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30197</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30197</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30197</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30197</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>TCP</td>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>Race list for UKGPL04.</td>
  </tr>
  <tr>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>UKGPL04</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>192.168.xxx.yyy</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30198</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30198</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30198</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30198</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>TCP</td>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'></td>
  </tr>
  <tr>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>UKGPL04</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>192.168.xxx.yyy</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30199</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30199</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30199</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30199</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>UDP</td>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>iGOR Broadcasting.</td>
  </tr>
  <tr>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>UKGPL04</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>192.168.xxx.yyy</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>32766</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>32786</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>32766</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>32786</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>UDP</td>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>UKGPL04 Race Connections.</td>
  </tr>
  <tr>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>UKGPL05</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>192.168.xxx.zzz</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30192</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30192</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30192</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>30192</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>TCP</td>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>iGOR remote hosting for UKGPL05 (this is different from the default port).</td>
  </tr>
  <tr>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>UKGPL05</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>192.168.xxx.zzz</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>32666</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>32686</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>32666</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>32686</td>
    <td align=center style='border-top: 1px solid #696969; border-collapse: collapse;'>UDP</td>
    <td style='border-top: 1px solid #696969; border-collapse: collapse;'>UKGPL05 Race Connections (these are different from the default ports).</td>
  </tr>
</table>
</DIV>
<P>
Notes: 
<ol>
  <li>When connecting via iGOR, drivers do not need to be concerned about the port settings. Similarly, when connecting to a server using the default ports via the GPL Multiplayer interface, drivers just need to type in the WAN IP address. However, when connecting to a server using alternative default ports via the GPL Multiplayer interface, drivers need to type in the (start) port number in addition to the WAN IP address. In the case of UKGPL05 this would be aaa.bbb.ccc.ddd:32666.</li>
  <li>Unfortunately the standard distribution of iGOR (Release 1.6) does not support 
  different ports. However there is a patched version available <a href="/files/iGOR_v1p6_Patched.zip">here</a> that does support different ports. Use the patched iGOR on the server that needs to use different ports (in this case on UKGPL05).</li>
</ol>  
</P>

