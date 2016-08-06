#!/bin/sh

#if [ "$REMOTE_ADDR" != "$SERVER_ADDR" ]; then
#	echo "Cannot be called externally."
#	exit
#fi

# From the AA archive gateway via http://hoohoo.ncsa.uiuc.edu/cgi/forms.html
# and http://www.shelldorado.com/scripts/cmds/urldecode.
#EncodedLF=yes
eval "$(echo -n $QUERY_STRING | sed -e 's/'"'"'/%27/g' | \
	      awk 'BEGIN{RS="&";FS="="}
		$1~/^[a-zA-Z][a-zA-Z0-9_]*$/ {
			printf "QS_%s=%c%s%c\n",$1,39,$2,39}' | \
awk '
    BEGIN {
	hextab ["0"] = 0;	hextab ["8"] = 8;
	hextab ["1"] = 1;	hextab ["9"] = 9;
	hextab ["2"] = 2;	hextab ["A"] = hextab ["a"] = 10
	hextab ["3"] = 3;	hextab ["B"] = hextab ["b"] = 11;
	hextab ["4"] = 4;	hextab ["C"] = hextab ["c"] = 12;
	hextab ["5"] = 5;	hextab ["D"] = hextab ["d"] = 13;
	hextab ["6"] = 6;	hextab ["E"] = hextab ["e"] = 14;
	hextab ["7"] = 7;	hextab ["F"] = hextab ["f"] = 15;
	if ("'"$EncodedLF"'" == "yes") EncodedLF = 1; else EncodedLF = 0
    }
    {
    	decoded = ""
	i   = 1
	len = length ($0)
	while ( i <= len ) {
	    c = substr ($0, i, 1)
	    if ( c == "%" ) {
	    	if ( i+2 <= len ) {
		    c1 = substr ($0, i+1, 1)
		    c2 = substr ($0, i+2, 1)
		    if ( hextab [c1] == "" || hextab [c2] == "" ) {
			print "WARNING: invalid hex encoding: %" c1 c2 | \
				"cat >&2"
		    } else {
		    	code = 0 + hextab [c1] * 16 + hextab [c2] + 0
		    	#print "\ncode=", code
		    	c = sprintf ("%c", code)
			i = i + 2
		    }
		} else {
		    print "WARNING: invalid % encoding: " substr ($0, i, len - i)
		}
	    } else if ( c == "+" ) {	# special handling: "+" means " "
	    	c = " "
	    }
	    decoded = decoded c
	    ++i
	}
	if ( EncodedLF ) {
	    printf "%s", decoded	# no line newline on output
	} else {
	    print decoded
	}
    }
')"

echo "Content-Type: text/plain"
if [ -n "$QS_name"  ]; then
	echo "Content-Disposition: attachment; filename=\"$QS_name\""
elif [ -n "$QS_vcr" ]; then
	echo "Content-Disposition: inline; filename=\"${QS_vcr}.txt\""
fi
echo

exec 2>&1

if [ -n "$QS_vcr" ]; then
	unzip -p "../$QS_zip" "$QS_vcr" | php -f report.php | awk "{printf(\"%s\r\n\",\$0)}"
elif [ -n "$QS_name" ]; then
	unzip -p "../$QS_zip" "$(echo "$QS_name" | sed -e 's/\[/\\\[/g' -e 's/\]/\\\]/g')"
	# Used to include "| dd bs=1024 count=150" to restrict the maximum size, until they locked dd down. :-(
else
	unzip -v "../$QS_zip"
fi