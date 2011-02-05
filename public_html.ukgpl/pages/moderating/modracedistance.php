<?php include("pages/moderating/index.php"); ?>

<STYLE TYPE="text/css">
<!--
.indented
   {
   padding-left: 50pt;
   padding-right: 50pt;
   }
-->
</STYLE>


<H1 ALIGN="CENTER">Modifying the Race Distance</H1>

<H2>How it works in GPL</H2>

<P>The Grand Prix race distance for each event is defined in the seasons\67season.ini file.  The other race distances are a calculated as a proportion of the Grand Prix race distance.  In essence, a long race is 0.3 times the Grand Prix distance and a short race is 0.3 times the long race distance.  However this proportion rarely works out to be an exact number of laps so the resulting calculation is always rounded up.  The following example for Spa Francorchamps illustrates the mechanism.</p>

<p>The standard Grand Prix distance at Spa is 28 laps over the 8.761 mile circuit.  This works out to be 245.308 miles.  The effect changing the Grand Prix length has on the number of laps calculated for a long race can be seen in the table below: </p>

<DIV CLASS="indented">
<table x:str border=2 cellpadding=0 cellspacing=0 width=605 style='border-collapse:
 collapse;table-layout:fixed;width:454pt'>
 <col width=75 span=2 style='mso-width-source:userset;mso-width-alt:2742;
 width:56pt'>
 <col width=136 style='mso-width-source:userset;mso-width-alt:4973;width:102pt'>
 <col width=142 style='mso-width-source:userset;mso-width-alt:5193;width:107pt'>
 <col width=64 style='width:48pt'>
 <col width=113 style='mso-width-source:userset;mso-width-alt:4132;width:85pt'>

 <tr height=17 style='height:12.75pt' align=center>
  <td>GP Laps</td>
  <td>0.3 x Laps</td>
  <td>GP Race Distance</td>
  <td>0.3 x GP Race Distance</td>
  <td>Laps</td>
  <td>Laps Rounded Up</td>
 </tr>
 <tr align=right>
  <td>20</td>
  <td>6</td>
  <td>175.22</td>
  <td>52.566</td>
  <td>6</td>
  <td>6</td>
 </tr>
 <tr align=right>
  <td>21</td>
  <td>6.3</td>
  <td>183.981</td>
  <td>55.1943</td>
  <td>6.3</td>
  <td>7</td>
 </tr>
 <tr align=right>
  <td>22</td>
  <td>6.6</td>
  <td>192.742</td>
  <td>57.8226</td>
  <td>6.6</td>
  <td>7</td>
 </tr>
 <tr align=right>
  <td>23</td>
  <td>6.9</td>
  <td>201.503</td>
  <td>60.4509</td>
  <td>6.9</td>
  <td>7</td>
 </tr>
 <tr align=right>
  <td>24</td>
  <td>7.2</td>
  <td>210.264</td>
  <td>63.0792</td>
  <td>7.2</td>
  <td>8</td>
 </tr>
 <tr align=right>
  <td>25</td>
  <td>7.5</td>
  <td>219.025</td>
  <td>65.7075</td>
  <td>7.5</td>
  <td>8</td>
 </tr>
 <tr align=right>
  <td>26</td>
  <td>7.8</td>
  <td>227.786</td>
  <td>68.3358</td>
  <td>7.8</td>
  <td>8</td>
 </tr>
 <tr align=right>
  <td>27</td>
  <td>8.1</td>
  <td>236.547</td>
  <td>70.9641</td>
  <td>8.1</td>
  <td>9</td>
 </tr>
 <tr align=right>
  <td>28</td>
  <td>8.4</td>
  <td>245.308</td>
  <td>73.5924</td>
  <td>8.4</td>
  <td>9</td>
 </tr>
 <tr align=right>
  <td>29</td>
  <td>8.7</td>
  <td>254.069</td>
  <td>76.2207</td>
  <td>8.7</td>
  <td>9</td>
 </tr>
 <tr align=right>
  <td>30</td>
  <td>9</td>
  <td>262.83</td>
  <td>78.849</td>
  <td>9</td>
  <td>9</td>
 </tr>
 <tr align=right>
  <td>31</td>
  <td>9.3</td>
  <td>271.591</td>
  <td>81.4773</td>
  <td>9.3</td>
  <td>10</td>
 </tr>
 <tr align=right>
  <td>32</td>
  <td>9.6</td>
  <td>280.352</td>
  <td>84.1056</td>
  <td>9.6</td>
  <td>10</td>
 </tr>
 <tr align=right>
  <td>33</td>
  <td>9.9</td>
  <td>289.113</td>
  <td>86.7339</td>
  <td>9.9</td>
  <td>10</td>
 </tr>
 <tr align=right>
  <td>34</td>
  <td>10.2</td>
  <td>297.874</td>
  <td>89.3622</td>
  <td>10.2</td>
  <td>11</td>
 </tr>
 <tr align=right>
  <td>35</td>
  <td>10.5</td>
  <td>306.635</td>
  <td>91.9905</td>
  <td>10.5</td>
  <td>11</td>
 </tr>
 <tr align=right>
  <td>36</td>
  <td>10.8</td>
  <td>315.396</td>
  <td>94.6188</td>
  <td>10.8</td>
  <td>11</td>
 </tr>
 <tr align=right>
  <td>37</td>
  <td>11.1</td>
  <td>324.157</td>
  <td>97.2471</td>
  <td>11.1</td>
  <td>12</td>
 </tr>
 <tr align=right>
  <td>38</td>
  <td>11.4</td>
  <td>332.918</td>
  <td>99.8754</td>
  <td>11.4</td>
  <td>12</td>
 </tr>
 <tr align=right>
  <td>39</td>
  <td>11.7</td>
  <td>341.679</td>
  <td>102.5037</td>
  <td>11.7</td>
  <td>12</td>
 </tr>
 <tr align=right>
  <td>40</td>
  <td>12</td>
  <td>350.44</td>
  <td>105.132</td>
  <td>12</td>
  <td>12</td>
 </tr>
 <tr align=right>
  <td>41</td>
  <td>12.3</td>
  <td>359.201</td>
  <td>107.7603</td>
  <td>12.3</td>
  <td>13</td>
 </tr>
 <tr align=right>
  <td>42</td>
  <td>12.6</td>
  <td>367.962</td>
  <td>110.3886</td>
  <td>12.6</td>
  <td>13</td>
 </tr>
 <tr align=right>
  <td>43</td>
  <td>12.9</td>
  <td>376.723</td>
  <td>113.0169</td>
  <td>12.9</td>
  <td>13</td>
 </tr>
 <tr align=right>
  <td>44</td>
  <td>13.2</td>
  <td>385.484</td>
  <td>115.6452</td>
  <td>13.2</td>
  <td>14</td>
 </tr>
 <tr align=right>
  <td>45</td>
  <td>13.5</td>
  <td>394.245</td>
  <td>118.2735</td>
  <td>13.5</td>
  <td>14</td>
 </tr>
 <tr align=right>
  <td>46</td>
  <td>13.8</td>
  <td>403.006</td>
  <td>120.9018</td>
  <td>13.8</td>
  <td>14</td>
 </tr>
 <tr align=right>
  <td>47</td>
  <td>14.1</td>
  <td>411.767</td>
  <td>123.5301</td>
  <td>14.1</td>
  <td>15</td>
 </tr>
 <tr align=right>
  <td>48</td>
  <td>14.4</td>
  <td>420.528</td>
  <td>126.1584</td>
  <td>14.4</td>
  <td>15</td>
 </tr>
 <tr align=right>
  <td>49</td>
  <td>14.7</td>
  <td>429.289</td>
  <td>128.7867</td>
  <td>14.7</td>
  <td>15</td>
 </tr>
 <tr align=right>
  <td>50</td>
  <td>15</td>
  <td>438.05</td>
  <td>131.415</td>
  <td>15</td>
  <td>15</td>
 </tr>
 </table>
</DIV>

<p>The standard Grand Prix distance of 28 laps equates to a long race distance of 9 laps.  Conversely a long race distance of 14 laps equates to a Grand Prix distance of either 44, 45 or 46 laps.  </p>

<H2>How to modify the race distance</H2>

<p>It is possible to set the Grand Prix distance to the number of required laps and simply run the race under Grand Prix rules.  This of course means using the PRO Damage model which may not be what is required.  In order to run a race with Intermediate damage then the the Grand Prix length in the seasons\67season.ini file has to be set to a value that ensures the long race distance equates to the desired number of laps.  In this way selecting a long race ensures the damage model can be set independently of the number of laps.</p>  

<p>Sometimes it is useful to calculate the number of laps required to run a race of given period of time.  This does require the selection of a representative lap time on which to base the calculation.  This technique is employed in the <a href="../../files/pit_stop_calculator.zip">Pit Stop Calculator</a> and is used to determine the number of laps required for the normal "Division 1" 50 minute race.  
