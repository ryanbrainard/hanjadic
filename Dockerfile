from base

maintainer Dan Bravender

run mkdir -p /opt/hanjadic
add . /opt/hanjadic

expose 8089

workdir /opt/hanjadic/bin
cmd ["/opt/hanjadic/bin/hanjadic"]
