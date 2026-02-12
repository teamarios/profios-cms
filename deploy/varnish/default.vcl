vcl 4.1;

backend default {
    .host = "nginx";
    .port = "80";
    .connect_timeout = 1s;
    .first_byte_timeout = 60s;
    .between_bytes_timeout = 30s;
}

sub vcl_recv {
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    if (req.url ~ "^/admin" || req.url ~ "^/setup") {
        return (pass);
    }

    if (req.http.Authorization || req.http.Cookie) {
        return (pass);
    }

    return (hash);
}

sub vcl_backend_response {
    if (beresp.http.Cache-Control ~ "no-store" || beresp.status >= 500) {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        return (deliver);
    }

    if (beresp.ttl <= 0s) {
        set beresp.ttl = 120s;
    }

    set beresp.grace = 6h;
}

sub vcl_deliver {
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
    } else {
        set resp.http.X-Cache = "MISS";
    }
}
