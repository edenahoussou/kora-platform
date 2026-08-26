<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>KORA Platform API Documentation</title>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.style.css") }}" media="screen">
    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.print.css") }}" media="print">

    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.10/lodash.min.js"></script>

    <link rel="stylesheet"
          href="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/styles/obsidian.min.css">
    <script src="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/highlight.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jets/0.14.1/jets.min.js"></script>

    <style id="language-style">
        /* starts out as display none and is replaced with js later  */
                    body .content .bash-example code { display: none; }
                    body .content .javascript-example code { display: none; }
            </style>

    <script>
        var tryItOutBaseUrl = "http://localhost:8000";
        var useCsrf = Boolean();
        var csrfUrl = "/sanctum/csrf-cookie";
    </script>
    <script src="{{ asset("/vendor/scribe/js/tryitout-5.11.0.js") }}"></script>

    <script src="{{ asset("/vendor/scribe/js/theme-default-5.11.0.js") }}"></script>

</head>

<body data-languages="[&quot;bash&quot;,&quot;javascript&quot;]">

<a href="#" id="nav-button">
    <span>
        MENU
        <img src="{{ asset("/vendor/scribe/images/navbar.png") }}" alt="navbar-image"/>
    </span>
</a>
<div class="tocify-wrapper">
    
            <div class="lang-selector">
                                            <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                            <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                    </div>
    
    <div class="search">
        <input type="text" class="search" id="input-search" placeholder="Search">
    </div>

    <div id="toc">
                    <ul id="tocify-header-introduction" class="tocify-header">
                <li class="tocify-item level-1" data-unique="introduction">
                    <a href="#introduction">Introduction</a>
                </li>
                            </ul>
                    <ul id="tocify-header-authenticating-requests" class="tocify-header">
                <li class="tocify-item level-1" data-unique="authenticating-requests">
                    <a href="#authenticating-requests">Authenticating requests</a>
                </li>
                            </ul>
                    <ul id="tocify-header-bus" class="tocify-header">
                <li class="tocify-item level-1" data-unique="bus">
                    <a href="#bus">Bus</a>
                </li>
                                    <ul id="tocify-subheader-bus" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="bus-GETapi-v1-bus-cities">
                                <a href="#bus-GETapi-v1-bus-cities">List served cities.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="bus-GETapi-v1-bus-lines">
                                <a href="#bus-GETapi-v1-bus-lines">List Bus lines.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="bus-GETapi-v1-bus-lines--uuid-">
                                <a href="#bus-GETapi-v1-bus-lines--uuid-">Show a Bus line.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="bus-GETapi-v1-bus-departures">
                                <a href="#bus-GETapi-v1-bus-departures">List departures.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="bus-GETapi-v1-bus-departures--uuid-">
                                <a href="#bus-GETapi-v1-bus-departures--uuid-">Show a departure.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="bus-GETapi-v1-bus-departures--departure_uuid--seats">
                                <a href="#bus-GETapi-v1-bus-departures--departure_uuid--seats">List departure seats.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="bus-POSTapi-v1-bus-quotes">
                                <a href="#bus-POSTapi-v1-bus-quotes">Create a fixed price quote.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="bus-POSTapi-v1-bus-holds">
                                <a href="#bus-POSTapi-v1-bus-holds">Hold seats for 15 minutes.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="bus-DELETEapi-v1-bus-holds--reservation_uuid-">
                                <a href="#bus-DELETEapi-v1-bus-holds--reservation_uuid-">Release a seat hold.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="bus-POSTapi-v1-bookings--reservation_uuid--passengers">
                                <a href="#bus-POSTapi-v1-bookings--reservation_uuid--passengers">Store passenger details for a reservation.</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-auth" class="tocify-header">
                <li class="tocify-item level-1" data-unique="auth">
                    <a href="#auth">Auth</a>
                </li>
                                    <ul id="tocify-subheader-auth" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-otp-request">
                                <a href="#auth-POSTapi-v1-auth-otp-request">POST api/v1/auth/otp/request</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-otp-verify">
                                <a href="#auth-POSTapi-v1-auth-otp-verify">POST api/v1/auth/otp/verify</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-pin-reset">
                                <a href="#auth-POSTapi-v1-auth-pin-reset">POST api/v1/auth/pin/reset</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-unlock">
                                <a href="#auth-POSTapi-v1-auth-unlock">POST api/v1/auth/unlock</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-register-complete">
                                <a href="#auth-POSTapi-v1-auth-register-complete">POST api/v1/auth/register/complete</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-login">
                                <a href="#auth-POSTapi-v1-auth-login">POST api/v1/auth/login</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-oauth-token">
                                <a href="#auth-POSTapi-v1-auth-oauth-token">Échange un jeton OAuth provider contre une session KORA.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-GETapi-v1-auth-me">
                                <a href="#auth-GETapi-v1-auth-me">GET api/v1/auth/me</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-PUTapi-v1-auth-me">
                                <a href="#auth-PUTapi-v1-auth-me">PUT api/v1/auth/me</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-pin-change">
                                <a href="#auth-POSTapi-v1-auth-pin-change">POST api/v1/auth/pin/change</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-GETapi-v1-auth-preferences">
                                <a href="#auth-GETapi-v1-auth-preferences">GET api/v1/auth/preferences</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-PUTapi-v1-auth-preferences">
                                <a href="#auth-PUTapi-v1-auth-preferences">PUT api/v1/auth/preferences</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-GETapi-v1-auth-devices">
                                <a href="#auth-GETapi-v1-auth-devices">GET api/v1/auth/devices</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-DELETEapi-v1-auth-devices--uuid-">
                                <a href="#auth-DELETEapi-v1-auth-devices--uuid-">DELETE api/v1/auth/devices/{uuid}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="auth-POSTapi-v1-auth-logout">
                                <a href="#auth-POSTapi-v1-auth-logout">POST api/v1/auth/logout</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-endpoints" class="tocify-header">
                <li class="tocify-item level-1" data-unique="endpoints">
                    <a href="#endpoints">Endpoints</a>
                </li>
                                    <ul id="tocify-subheader-endpoints" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-bookings--reservation_uuid--tickets">
                                <a href="#endpoints-GETapi-v1-bookings--reservation_uuid--tickets">GET api/v1/bookings/{reservation_uuid}/tickets</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-tickets--uuid-">
                                <a href="#endpoints-GETapi-v1-tickets--uuid-">GET api/v1/tickets/{uuid}</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-health" class="tocify-header">
                <li class="tocify-item level-1" data-unique="health">
                    <a href="#health">Health</a>
                </li>
                                    <ul id="tocify-subheader-health" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="health-GETapi-v1-health">
                                <a href="#health-GETapi-v1-health">Full application health: database, cache (Redis), and API version.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="health-GETapi-v1-health-ping">
                                <a href="#health-GETapi-v1-health-ping">Lightweight liveness probe for load balancers: always 200 when the
process can answer, with no dependency checks.</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-payments" class="tocify-header">
                <li class="tocify-item level-1" data-unique="payments">
                    <a href="#payments">Payments</a>
                </li>
                                    <ul id="tocify-subheader-payments" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="payments-GETapi-v1-payments-methods">
                                <a href="#payments-GETapi-v1-payments-methods">Return the active payment methods and providers configured for the mobile app.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="payments-POSTapi-v1-payments-initiate">
                                <a href="#payments-POSTapi-v1-payments-initiate">Initiate a payment for a held reservation.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="payments-GETapi-v1-payments--uuid-">
                                <a href="#payments-GETapi-v1-payments--uuid-">Get payment status (polling).</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="payments-POSTapi-v1-payments--payment_uuid--verify">
                                <a href="#payments-POSTapi-v1-payments--payment_uuid--verify">Explicitly verify a pending payment with its provider.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="payments-POSTapi-v1-payments--payment_uuid--cancel">
                                <a href="#payments-POSTapi-v1-payments--payment_uuid--cancel">POST api/v1/payments/{payment_uuid}/cancel</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="payments-POSTapi-v1-payments--payment_uuid--confirm">
                                <a href="#payments-POSTapi-v1-payments--payment_uuid--confirm">Provider webhook / callback to confirm or fail a payment.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="payments-POSTapi-v1-payments-webhook-fedapay">
                                <a href="#payments-POSTapi-v1-payments-webhook-fedapay">FedaPay webhook endpoint.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="payments-POSTapi-v1-payments-webhook-mtn-momo">
                                <a href="#payments-POSTapi-v1-payments-webhook-mtn-momo">MTN MoMo webhook endpoint.</a>
                            </li>
                                                                        </ul>
                            </ul>
            </div>

    <ul class="toc-footer" id="toc-footer">
                    <li style="padding-bottom: 5px;"><a href="{{ route("scribe.postman") }}">View Postman collection</a></li>
                            <li style="padding-bottom: 5px;"><a href="{{ route("scribe.openapi") }}">View OpenAPI spec</a></li>
                <li><a href="http://github.com/knuckleswtf/scribe">Documentation powered by Scribe ✍</a></li>
    </ul>

    <ul class="toc-footer" id="last-updated">
        <li>Last updated: August 26, 2026</li>
    </ul>
</div>

<div class="page-wrapper">
    <div class="dark-box"></div>
    <div class="content">
        <h1 id="introduction">Introduction</h1>
<p>KORA BUS MVP REST API.</p>
<aside>
    <strong>Base URL</strong>: <code>http://localhost:8000</code>
</aside>
<pre><code>KORA BUS MVP exposes public read endpoints for Bus search and protected operational endpoints as the product grows.

Public identifiers are UUIDs or domain codes. Numeric backend IDs are never part of public payloads.</code></pre>

        <h1 id="authenticating-requests">Authenticating requests</h1>
<p>To authenticate requests, include an <strong><code>Authorization</code></strong> header with the value <strong><code>"Bearer {YOUR_AUTH_KEY}"</code></strong>.</p>
<p>All authenticated endpoints are marked with a <code>requires authentication</code> badge in the documentation below.</p>
<p>You can retrieve your token by visiting your dashboard and clicking <b>Generate API token</b>.</p>

        <h1 id="bus">Bus</h1>

    <p>Public Bus catalog endpoints for KORA BUS search experiences.</p>

                                <h2 id="bus-GETapi-v1-bus-cities">List served cities.</h2>

<p>
</p>

<p>Returns active cities derived from active Bus stops.</p>

<span id="example-requests-GETapi-v1-bus-cities">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/bus/cities" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/bus/cities"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-bus-cities">
            <blockquote>
            <p>Example response (200, Cities list):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;city&quot;: &quot;Cotonou&quot;,
            &quot;country&quot;: &quot;BJ&quot;,
            &quot;stops_count&quot;: 4
        },
        {
            &quot;city&quot;: &quot;Abidjan&quot;,
            &quot;country&quot;: &quot;CI&quot;,
            &quot;stops_count&quot;: 2
        }
    ]
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-bus-cities" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-bus-cities"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-bus-cities"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-bus-cities" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-bus-cities">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-bus-cities" data-method="GET"
      data-path="api/v1/bus/cities"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-bus-cities', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-bus-cities"
                    onclick="tryItOut('GETapi-v1-bus-cities');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-bus-cities"
                    onclick="cancelTryOut('GETapi-v1-bus-cities');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-bus-cities"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/bus/cities</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-bus-cities"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-bus-cities"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="bus-GETapi-v1-bus-lines">List Bus lines.</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-bus-lines">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/bus/lines?from=Cotonou&amp;to=Abidjan" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"from\": \"b\",
    \"to\": \"n\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/bus/lines"
);

const params = {
    "from": "Cotonou",
    "to": "Abidjan",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "from": "b",
    "to": "n"
};

fetch(url, {
    method: "GET",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-bus-lines">
            <blockquote>
            <p>Example response (200, Lines list):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;uuid&quot;: &quot;9c2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e1f&quot;,
            &quot;code&quot;: &quot;L1&quot;,
            &quot;name&quot;: &quot;Cotonou - Abidjan&quot;,
            &quot;type&quot;: &quot;internationale&quot;,
            &quot;countries_crossed&quot;: [
                &quot;BJ&quot;,
                &quot;TG&quot;,
                &quot;GH&quot;,
                &quot;CI&quot;
            ],
            &quot;distance_km&quot;: 1020,
            &quot;estimated_duration_minutes&quot;: 1260,
            &quot;status&quot;: &quot;active&quot;,
            &quot;stops&quot;: []
        }
    ]
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-bus-lines" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-bus-lines"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-bus-lines"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-bus-lines" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-bus-lines">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-bus-lines" data-method="GET"
      data-path="api/v1/bus/lines"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-bus-lines', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-bus-lines"
                    onclick="tryItOut('GETapi-v1-bus-lines');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-bus-lines"
                    onclick="cancelTryOut('GETapi-v1-bus-lines');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-bus-lines"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/bus/lines</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-bus-lines"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-bus-lines"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>from</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="from"                data-endpoint="GETapi-v1-bus-lines"
               value="Cotonou"
               data-component="query">
    <br>
<p>Optional origin city filter. Example: <code>Cotonou</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>to</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="to"                data-endpoint="GETapi-v1-bus-lines"
               value="Abidjan"
               data-component="query">
    <br>
<p>Optional destination city filter. Example: <code>Abidjan</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>from</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="from"                data-endpoint="GETapi-v1-bus-lines"
               value="b"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 120 caractères. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>to</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="to"                data-endpoint="GETapi-v1-bus-lines"
               value="n"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 120 caractères. Example: <code>n</code></p>
        </div>
        </form>

                    <h2 id="bus-GETapi-v1-bus-lines--uuid-">Show a Bus line.</h2>

<p>
</p>

<p>Includes the line's active stops, ordered along the route.</p>

<span id="example-requests-GETapi-v1-bus-lines--uuid-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/bus/lines/f3e58eb2-66aa-4fee-9e98-f5dd59054e7e" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/bus/lines/f3e58eb2-66aa-4fee-9e98-f5dd59054e7e"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-bus-lines--uuid-">
            <blockquote>
            <p>Example response (200, Line detail):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;uuid&quot;: &quot;9c2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e1f&quot;,
        &quot;code&quot;: &quot;L1&quot;,
        &quot;name&quot;: &quot;Cotonou - Abidjan&quot;,
        &quot;type&quot;: &quot;internationale&quot;,
        &quot;countries_crossed&quot;: [
            &quot;BJ&quot;,
            &quot;TG&quot;,
            &quot;GH&quot;,
            &quot;CI&quot;
        ],
        &quot;distance_km&quot;: 1020,
        &quot;estimated_duration_minutes&quot;: 1260,
        &quot;status&quot;: &quot;active&quot;,
        &quot;stops&quot;: [
            {
                &quot;uuid&quot;: &quot;5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e21&quot;,
                &quot;name&quot;: &quot;Cotonou &middot; St Michel&quot;,
                &quot;city&quot;: &quot;Cotonou&quot;,
                &quot;country&quot;: &quot;BJ&quot;,
                &quot;stop_order&quot;: 1,
                &quot;km_from_origin&quot;: 0
            },
            {
                &quot;uuid&quot;: &quot;5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e22&quot;,
                &quot;name&quot;: &quot;Abidjan &middot; Adjam&eacute;&quot;,
                &quot;city&quot;: &quot;Abidjan&quot;,
                &quot;country&quot;: &quot;CI&quot;,
                &quot;stop_order&quot;: 2,
                &quot;km_from_origin&quot;: 1020
            }
        ]
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Line not found):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;No query results for model [App\\Modules\\BusNetwork\\Models\\Line].&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-bus-lines--uuid-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-bus-lines--uuid-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-bus-lines--uuid-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-bus-lines--uuid-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-bus-lines--uuid-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-bus-lines--uuid-" data-method="GET"
      data-path="api/v1/bus/lines/{uuid}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-bus-lines--uuid-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-bus-lines--uuid-"
                    onclick="tryItOut('GETapi-v1-bus-lines--uuid-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-bus-lines--uuid-"
                    onclick="cancelTryOut('GETapi-v1-bus-lines--uuid-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-bus-lines--uuid-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/bus/lines/{uuid}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-bus-lines--uuid-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-bus-lines--uuid-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="uuid"                data-endpoint="GETapi-v1-bus-lines--uuid-"
               value="f3e58eb2-66aa-4fee-9e98-f5dd59054e7e"
               data-component="url">
    <br>
<p>Example: <code>f3e58eb2-66aa-4fee-9e98-f5dd59054e7e</code></p>
            </div>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>line</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="line"                data-endpoint="GETapi-v1-bus-lines--uuid-"
               value="9c2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e1f"
               data-component="url">
    <br>
<p>Public UUID of the line. Example: <code>9c2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e1f</code></p>
            </div>
                    </form>

                    <h2 id="bus-GETapi-v1-bus-departures">List departures.</h2>

<p>
</p>

<p>Paginated list of scheduled departures (15 per page by default).</p>

<span id="example-requests-GETapi-v1-bus-departures">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/bus/departures?date=2026-08-15&amp;line=9c2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e1f&amp;origin_city=Cotonou&amp;destination_city=Abidjan&amp;from=Cotonou&amp;to=Abidjan&amp;per_page=15&amp;page=1" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"date\": \"2026-08-26\",
    \"line\": \"6ff8f7f6-1eb3-3525-be4a-3932c805afed\",
    \"from\": \"g\",
    \"to\": \"z\",
    \"origin_city\": \"m\",
    \"destination_city\": \"i\",
    \"per_page\": 8,
    \"page\": 16
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/bus/departures"
);

const params = {
    "date": "2026-08-15",
    "line": "9c2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e1f",
    "origin_city": "Cotonou",
    "destination_city": "Abidjan",
    "from": "Cotonou",
    "to": "Abidjan",
    "per_page": "15",
    "page": "1",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "date": "2026-08-26",
    "line": "6ff8f7f6-1eb3-3525-be4a-3932c805afed",
    "from": "g",
    "to": "z",
    "origin_city": "m",
    "destination_city": "i",
    "per_page": 8,
    "page": 16
};

fetch(url, {
    method: "GET",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-bus-departures">
            <blockquote>
            <p>Example response (200, Departures list):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;uuid&quot;: &quot;9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e&quot;,
            &quot;departure_date&quot;: &quot;2026-08-15&quot;,
            &quot;departure_time&quot;: &quot;07:30&quot;,
            &quot;estimated_arrival_at&quot;: &quot;2026-08-16T04:30:00.000000Z&quot;,
            &quot;status&quot;: &quot;scheduled&quot;,
            &quot;line&quot;: {
                &quot;uuid&quot;: &quot;9c2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e1f&quot;,
                &quot;code&quot;: &quot;L1&quot;,
                &quot;name&quot;: &quot;Cotonou - Abidjan&quot;,
                &quot;type&quot;: &quot;internationale&quot;,
                &quot;countries_crossed&quot;: [
                    &quot;BJ&quot;,
                    &quot;TG&quot;,
                    &quot;GH&quot;,
                    &quot;CI&quot;
                ],
                &quot;distance_km&quot;: 1020,
                &quot;estimated_duration_minutes&quot;: 1260,
                &quot;status&quot;: &quot;active&quot;,
                &quot;stops&quot;: []
            },
            &quot;bus&quot;: {
                &quot;uuid&quot;: &quot;3a2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e30&quot;,
                &quot;registration_number&quot;: &quot;BJ-9254-RB&quot;,
                &quot;brand&quot;: &quot;KORA&quot;,
                &quot;model&quot;: &quot;Coach 55&quot;,
                &quot;seat_count&quot;: 55,
                &quot;layout&quot;: [
                    {
                        &quot;type&quot;: &quot;row&quot;,
                        &quot;codes&quot;: [
                            &quot;1A&quot;,
                            &quot;1B&quot;,
                            &quot;1C&quot;,
                            &quot;1D&quot;
                        ]
                    },
                    {
                        &quot;type&quot;: &quot;row&quot;,
                        &quot;codes&quot;: [
                            &quot;2A&quot;,
                            &quot;2B&quot;,
                            &quot;2C&quot;,
                            &quot;2D&quot;
                        ]
                    }
                ]
            },
            &quot;origin_stop&quot;: {
                &quot;uuid&quot;: &quot;5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e21&quot;,
                &quot;name&quot;: &quot;Cotonou &middot; St Michel&quot;,
                &quot;city&quot;: &quot;Cotonou&quot;,
                &quot;country&quot;: &quot;BJ&quot;,
                &quot;stop_order&quot;: 1,
                &quot;km_from_origin&quot;: 0
            },
            &quot;destination_stop&quot;: {
                &quot;uuid&quot;: &quot;5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e22&quot;,
                &quot;name&quot;: &quot;Abidjan &middot; Adjam&eacute;&quot;,
                &quot;city&quot;: &quot;Abidjan&quot;,
                &quot;country&quot;: &quot;CI&quot;,
                &quot;stop_order&quot;: 2,
                &quot;km_from_origin&quot;: 1020
            },
            &quot;available_seats_count&quot;: 41,
            &quot;fare_preview&quot;: {
                &quot;fare_uuid&quot;: &quot;7d2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e40&quot;,
                &quot;base_amount&quot;: 12500,
                &quot;final_amount&quot;: 12500,
                &quot;currency&quot;: &quot;XOF&quot;,
                &quot;service_class_code&quot;: &quot;STD&quot;,
                &quot;service_class_name&quot;: &quot;Standard&quot;,
                &quot;service_class_description&quot;: &quot;Si&egrave;ges inclinables, climatisation, prise USB.&quot;,
                &quot;is_quote&quot;: false
            }
        }
    ],
    &quot;links&quot;: {
        &quot;first&quot;: &quot;http://kora-ops-web.local/api/v1/bus/departures?page=1&quot;,
        &quot;last&quot;: &quot;http://kora-ops-web.local/api/v1/bus/departures?page=1&quot;,
        &quot;prev&quot;: null,
        &quot;next&quot;: null
    },
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;from&quot;: 1,
        &quot;last_page&quot;: 1,
        &quot;path&quot;: &quot;http://kora-ops-web.local/api/v1/bus/departures&quot;,
        &quot;per_page&quot;: 15,
        &quot;to&quot;: 1,
        &quot;total&quot;: 1
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Missing required date):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;The date field is required.&quot;,
    &quot;errors&quot;: {
        &quot;date&quot;: [
            &quot;The date field is required.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-bus-departures" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-bus-departures"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-bus-departures"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-bus-departures" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-bus-departures">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-bus-departures" data-method="GET"
      data-path="api/v1/bus/departures"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-bus-departures', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-bus-departures"
                    onclick="tryItOut('GETapi-v1-bus-departures');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-bus-departures"
                    onclick="cancelTryOut('GETapi-v1-bus-departures');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-bus-departures"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/bus/departures</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-bus-departures"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-bus-departures"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>date</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="date"                data-endpoint="GETapi-v1-bus-departures"
               value="2026-08-15"
               data-component="query">
    <br>
<p>Travel date in YYYY-MM-DD format. Example: <code>2026-08-15</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>line</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="line"                data-endpoint="GETapi-v1-bus-departures"
               value="9c2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e1f"
               data-component="query">
    <br>
<p>Optional line UUID filter. Example: <code>9c2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e1f</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>origin_city</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="origin_city"                data-endpoint="GETapi-v1-bus-departures"
               value="Cotonou"
               data-component="query">
    <br>
<p>Optional origin city filter (alias of from). Example: <code>Cotonou</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>destination_city</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="destination_city"                data-endpoint="GETapi-v1-bus-departures"
               value="Abidjan"
               data-component="query">
    <br>
<p>Optional destination city filter (alias of to). Example: <code>Abidjan</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>from</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="from"                data-endpoint="GETapi-v1-bus-departures"
               value="Cotonou"
               data-component="query">
    <br>
<p>Optional origin city filter. Example: <code>Cotonou</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>to</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="to"                data-endpoint="GETapi-v1-bus-departures"
               value="Abidjan"
               data-component="query">
    <br>
<p>Optional destination city filter. Example: <code>Abidjan</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-v1-bus-departures"
               value="15"
               data-component="query">
    <br>
<p>Optional page size (max 50, default 15). Example: <code>15</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-v1-bus-departures"
               value="1"
               data-component="query">
    <br>
<p>Optional page number. Example: <code>1</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>date</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="date"                data-endpoint="GETapi-v1-bus-departures"
               value="2026-08-26"
               data-component="body">
    <br>
<p>Must be a valid date in the format <code>Y-m-d</code>. Example: <code>2026-08-26</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>line</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="line"                data-endpoint="GETapi-v1-bus-departures"
               value="6ff8f7f6-1eb3-3525-be4a-3932c805afed"
               data-component="body">
    <br>
<p>validation.uuid Must match an existing stored value. Example: <code>6ff8f7f6-1eb3-3525-be4a-3932c805afed</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>from</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="from"                data-endpoint="GETapi-v1-bus-departures"
               value="g"
               data-component="body">
    <br>
<p>validation.max. Example: <code>g</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>to</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="to"                data-endpoint="GETapi-v1-bus-departures"
               value="z"
               data-component="body">
    <br>
<p>validation.max. Example: <code>z</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>origin_city</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="origin_city"                data-endpoint="GETapi-v1-bus-departures"
               value="m"
               data-component="body">
    <br>
<p>validation.max. Example: <code>m</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>destination_city</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="destination_city"                data-endpoint="GETapi-v1-bus-departures"
               value="i"
               data-component="body">
    <br>
<p>validation.max. Example: <code>i</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-v1-bus-departures"
               value="8"
               data-component="body">
    <br>
<p>validation.min validation.max. Example: <code>8</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-v1-bus-departures"
               value="16"
               data-component="body">
    <br>
<p>validation.min. Example: <code>16</code></p>
        </div>
        </form>

                    <h2 id="bus-GETapi-v1-bus-departures--uuid-">Show a departure.</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-bus-departures--uuid-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/bus/departures/48cc889a-8518-4cb2-b4ec-13e225ad597b?from=Cotonou&amp;to=Abidjan" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"from\": \"b\",
    \"to\": \"n\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/bus/departures/48cc889a-8518-4cb2-b4ec-13e225ad597b"
);

const params = {
    "from": "Cotonou",
    "to": "Abidjan",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "from": "b",
    "to": "n"
};

fetch(url, {
    method: "GET",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-bus-departures--uuid-">
            <blockquote>
            <p>Example response (200, Departure detail):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;uuid&quot;: &quot;9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e&quot;,
        &quot;departure_date&quot;: &quot;2026-08-15&quot;,
        &quot;departure_time&quot;: &quot;07:30&quot;,
        &quot;estimated_arrival_at&quot;: &quot;2026-08-16T04:30:00.000000Z&quot;,
        &quot;status&quot;: &quot;scheduled&quot;,
        &quot;line&quot;: {
            &quot;uuid&quot;: &quot;9c2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e1f&quot;,
            &quot;code&quot;: &quot;L1&quot;,
            &quot;name&quot;: &quot;Cotonou - Abidjan&quot;,
            &quot;type&quot;: &quot;internationale&quot;,
            &quot;countries_crossed&quot;: [
                &quot;BJ&quot;,
                &quot;TG&quot;,
                &quot;GH&quot;,
                &quot;CI&quot;
            ],
            &quot;distance_km&quot;: 1020,
            &quot;estimated_duration_minutes&quot;: 1260,
            &quot;status&quot;: &quot;active&quot;,
            &quot;stops&quot;: []
        },
        &quot;bus&quot;: {
            &quot;uuid&quot;: &quot;3a2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e30&quot;,
            &quot;registration_number&quot;: &quot;BJ-9254-RB&quot;,
            &quot;brand&quot;: &quot;KORA&quot;,
            &quot;model&quot;: &quot;Coach 55&quot;,
            &quot;seat_count&quot;: 55,
            &quot;layout&quot;: [
                {
                    &quot;type&quot;: &quot;row&quot;,
                    &quot;codes&quot;: [
                        &quot;1A&quot;,
                        &quot;1B&quot;,
                        &quot;1C&quot;,
                        &quot;1D&quot;
                    ]
                },
                {
                    &quot;type&quot;: &quot;row&quot;,
                    &quot;codes&quot;: [
                        &quot;2A&quot;,
                        &quot;2B&quot;,
                        &quot;2C&quot;,
                        &quot;2D&quot;
                    ]
                }
            ]
        },
        &quot;origin_stop&quot;: {
            &quot;uuid&quot;: &quot;5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e21&quot;,
            &quot;name&quot;: &quot;Cotonou &middot; St Michel&quot;,
            &quot;city&quot;: &quot;Cotonou&quot;,
            &quot;country&quot;: &quot;BJ&quot;,
            &quot;stop_order&quot;: 1,
            &quot;km_from_origin&quot;: 0
        },
        &quot;destination_stop&quot;: {
            &quot;uuid&quot;: &quot;5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e22&quot;,
            &quot;name&quot;: &quot;Abidjan &middot; Adjam&eacute;&quot;,
            &quot;city&quot;: &quot;Abidjan&quot;,
            &quot;country&quot;: &quot;CI&quot;,
            &quot;stop_order&quot;: 2,
            &quot;km_from_origin&quot;: 1020
        },
        &quot;available_seats_count&quot;: 41,
        &quot;fare_preview&quot;: {
            &quot;fare_uuid&quot;: &quot;7d2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e40&quot;,
            &quot;base_amount&quot;: 12500,
            &quot;final_amount&quot;: 12500,
            &quot;currency&quot;: &quot;XOF&quot;,
            &quot;service_class_code&quot;: &quot;STD&quot;,
            &quot;service_class_name&quot;: &quot;Standard&quot;,
            &quot;service_class_description&quot;: &quot;Si&egrave;ges inclinables, climatisation, prise USB.&quot;,
            &quot;is_quote&quot;: false
        }
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Departure not found):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;No query results for model [App\\Modules\\Departures\\Models\\Departure].&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-bus-departures--uuid-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-bus-departures--uuid-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-bus-departures--uuid-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-bus-departures--uuid-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-bus-departures--uuid-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-bus-departures--uuid-" data-method="GET"
      data-path="api/v1/bus/departures/{uuid}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-bus-departures--uuid-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-bus-departures--uuid-"
                    onclick="tryItOut('GETapi-v1-bus-departures--uuid-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-bus-departures--uuid-"
                    onclick="cancelTryOut('GETapi-v1-bus-departures--uuid-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-bus-departures--uuid-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/bus/departures/{uuid}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-bus-departures--uuid-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-bus-departures--uuid-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="uuid"                data-endpoint="GETapi-v1-bus-departures--uuid-"
               value="48cc889a-8518-4cb2-b4ec-13e225ad597b"
               data-component="url">
    <br>
<p>Example: <code>48cc889a-8518-4cb2-b4ec-13e225ad597b</code></p>
            </div>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>departure</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="departure"                data-endpoint="GETapi-v1-bus-departures--uuid-"
               value="9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e"
               data-component="url">
    <br>
<p>Public UUID of the departure. Example: <code>9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>from</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="from"                data-endpoint="GETapi-v1-bus-departures--uuid-"
               value="Cotonou"
               data-component="query">
    <br>
<p>Optional origin city. Example: <code>Cotonou</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>to</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="to"                data-endpoint="GETapi-v1-bus-departures--uuid-"
               value="Abidjan"
               data-component="query">
    <br>
<p>Optional destination city. Example: <code>Abidjan</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>from</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="from"                data-endpoint="GETapi-v1-bus-departures--uuid-"
               value="b"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 120 caractères. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>to</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="to"                data-endpoint="GETapi-v1-bus-departures--uuid-"
               value="n"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 120 caractères. Example: <code>n</code></p>
        </div>
        </form>

                    <h2 id="bus-GETapi-v1-bus-departures--departure_uuid--seats">List departure seats.</h2>

<p>
</p>

<p>Returns every seat on the assigned bus, with its availability status for
the requested origin/destination segment (available, held, occupied, or blocked).</p>

<span id="example-requests-GETapi-v1-bus-departures--departure_uuid--seats">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/bus/departures/48cc889a-8518-4cb2-b4ec-13e225ad597b/seats?origin_stop_uuid=5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e21&amp;destination_stop_uuid=5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e22" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"origin_stop_uuid\": \"6ff8f7f6-1eb3-3525-be4a-3932c805afed\",
    \"destination_stop_uuid\": \"6b72fe4a-5b40-307c-bc24-f79acf9a1bb9\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/bus/departures/48cc889a-8518-4cb2-b4ec-13e225ad597b/seats"
);

const params = {
    "origin_stop_uuid": "5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e21",
    "destination_stop_uuid": "5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e22",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "origin_stop_uuid": "6ff8f7f6-1eb3-3525-be4a-3932c805afed",
    "destination_stop_uuid": "6b72fe4a-5b40-307c-bc24-f79acf9a1bb9"
};

fetch(url, {
    method: "GET",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-bus-departures--departure_uuid--seats">
            <blockquote>
            <p>Example response (200, Seat map):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;uuid&quot;: &quot;6e2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e50&quot;,
            &quot;code&quot;: &quot;1A&quot;,
            &quot;type&quot;: &quot;standard&quot;,
            &quot;sellable&quot;: true,
            &quot;availability_status&quot;: &quot;available&quot;,
            &quot;held_until&quot;: null
        },
        {
            &quot;uuid&quot;: &quot;6e2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e51&quot;,
            &quot;code&quot;: &quot;1B&quot;,
            &quot;type&quot;: &quot;standard&quot;,
            &quot;sellable&quot;: true,
            &quot;availability_status&quot;: &quot;held&quot;,
            &quot;held_until&quot;: &quot;2026-08-15T07:20:00.000000Z&quot;
        },
        {
            &quot;uuid&quot;: &quot;6e2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e52&quot;,
            &quot;code&quot;: &quot;1C&quot;,
            &quot;type&quot;: &quot;standard&quot;,
            &quot;sellable&quot;: true,
            &quot;availability_status&quot;: &quot;occupied&quot;,
            &quot;held_until&quot;: null
        }
    ]
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Invalid segment):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;The requested origin and destination do not form a valid ordered segment for this line.&quot;,
    &quot;errors&quot;: {
        &quot;segment&quot;: [
            &quot;The requested origin and destination do not form a valid ordered segment for this line.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-bus-departures--departure_uuid--seats" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-bus-departures--departure_uuid--seats"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-bus-departures--departure_uuid--seats"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-bus-departures--departure_uuid--seats" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-bus-departures--departure_uuid--seats">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-bus-departures--departure_uuid--seats" data-method="GET"
      data-path="api/v1/bus/departures/{departure_uuid}/seats"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-bus-departures--departure_uuid--seats', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-bus-departures--departure_uuid--seats"
                    onclick="tryItOut('GETapi-v1-bus-departures--departure_uuid--seats');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-bus-departures--departure_uuid--seats"
                    onclick="cancelTryOut('GETapi-v1-bus-departures--departure_uuid--seats');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-bus-departures--departure_uuid--seats"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/bus/departures/{departure_uuid}/seats</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-bus-departures--departure_uuid--seats"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-bus-departures--departure_uuid--seats"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>departure_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="departure_uuid"                data-endpoint="GETapi-v1-bus-departures--departure_uuid--seats"
               value="48cc889a-8518-4cb2-b4ec-13e225ad597b"
               data-component="url">
    <br>
<p>Example: <code>48cc889a-8518-4cb2-b4ec-13e225ad597b</code></p>
            </div>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>departure</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="departure"                data-endpoint="GETapi-v1-bus-departures--departure_uuid--seats"
               value="9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e"
               data-component="url">
    <br>
<p>Public UUID of the departure. Example: <code>9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>origin_stop_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="origin_stop_uuid"                data-endpoint="GETapi-v1-bus-departures--departure_uuid--seats"
               value="5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e21"
               data-component="query">
    <br>
<p>Public UUID of the boarding stop. Example: <code>5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e21</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>destination_stop_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="destination_stop_uuid"                data-endpoint="GETapi-v1-bus-departures--departure_uuid--seats"
               value="5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e22"
               data-component="query">
    <br>
<p>Public UUID of the drop-off stop. Example: <code>5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e22</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>origin_stop_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="origin_stop_uuid"                data-endpoint="GETapi-v1-bus-departures--departure_uuid--seats"
               value="6ff8f7f6-1eb3-3525-be4a-3932c805afed"
               data-component="body">
    <br>
<p>Le champ value doit être un UUID valide. Example: <code>6ff8f7f6-1eb3-3525-be4a-3932c805afed</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>destination_stop_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="destination_stop_uuid"                data-endpoint="GETapi-v1-bus-departures--departure_uuid--seats"
               value="6b72fe4a-5b40-307c-bc24-f79acf9a1bb9"
               data-component="body">
    <br>
<p>Le champ value doit être un UUID valide. Example: <code>6b72fe4a-5b40-307c-bc24-f79acf9a1bb9</code></p>
        </div>
        </form>

                    <h2 id="bus-POSTapi-v1-bus-quotes">Create a fixed price quote.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Requires a Sanctum bearer token.</p>

<span id="example-requests-POSTapi-v1-bus-quotes">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/bus/quotes" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"departure_uuid\": \"6ff8f7f6-1eb3-3525-be4a-3932c805afed\",
    \"origin_stop_uuid\": \"6b72fe4a-5b40-307c-bc24-f79acf9a1bb9\",
    \"destination_stop_uuid\": \"977e5426-8d13-3824-86aa-b092f8ae52c5\",
    \"service_class_uuid\": \"d6fa562b-acd5-35ff-babb-d11194d3737b\",
    \"passenger_count\": 6
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/bus/quotes"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "departure_uuid": "6ff8f7f6-1eb3-3525-be4a-3932c805afed",
    "origin_stop_uuid": "6b72fe4a-5b40-307c-bc24-f79acf9a1bb9",
    "destination_stop_uuid": "977e5426-8d13-3824-86aa-b092f8ae52c5",
    "service_class_uuid": "d6fa562b-acd5-35ff-babb-d11194d3737b",
    "passenger_count": 6
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-bus-quotes">
            <blockquote>
            <p>Example response (201, Quote created):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;uuid&quot;: &quot;9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d&quot;,
        &quot;departure_uuid&quot;: &quot;9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e&quot;,
        &quot;base_amount&quot;: 12500,
        &quot;coefficient&quot;: 1,
        &quot;final_amount&quot;: 12500,
        &quot;currency&quot;: &quot;XOF&quot;,
        &quot;passenger_count&quot;: 1,
        &quot;total_amount&quot;: 12500,
        &quot;expires_at&quot;: &quot;2026-08-20T08:10:00Z&quot;,
        &quot;snapshot&quot;: {
            &quot;fare_uuid&quot;: &quot;9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6f&quot;,
            &quot;line_code&quot;: &quot;L2&quot;,
            &quot;origin_stop&quot;: &quot;Cotonou &middot; St Michel&quot;,
            &quot;destination_stop&quot;: &quot;Malanville&quot;,
            &quot;service_class&quot;: &quot;Standard&quot;,
            &quot;passenger_count&quot;: 1,
            &quot;total_amount&quot;: 12500
        }
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-bus-quotes" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-bus-quotes"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-bus-quotes"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-bus-quotes" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-bus-quotes">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-bus-quotes" data-method="POST"
      data-path="api/v1/bus/quotes"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-bus-quotes', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-bus-quotes"
                    onclick="tryItOut('POSTapi-v1-bus-quotes');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-bus-quotes"
                    onclick="cancelTryOut('POSTapi-v1-bus-quotes');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-bus-quotes"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/bus/quotes</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-bus-quotes"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-bus-quotes"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-bus-quotes"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>departure_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="departure_uuid"                data-endpoint="POSTapi-v1-bus-quotes"
               value="6ff8f7f6-1eb3-3525-be4a-3932c805afed"
               data-component="body">
    <br>
<p>Le champ value doit être un UUID valide. Must match an existing stored value. Example: <code>6ff8f7f6-1eb3-3525-be4a-3932c805afed</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>origin_stop_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="origin_stop_uuid"                data-endpoint="POSTapi-v1-bus-quotes"
               value="6b72fe4a-5b40-307c-bc24-f79acf9a1bb9"
               data-component="body">
    <br>
<p>Le champ value doit être un UUID valide. Must match an existing stored value. Example: <code>6b72fe4a-5b40-307c-bc24-f79acf9a1bb9</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>destination_stop_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="destination_stop_uuid"                data-endpoint="POSTapi-v1-bus-quotes"
               value="977e5426-8d13-3824-86aa-b092f8ae52c5"
               data-component="body">
    <br>
<p>Le champ value doit être un UUID valide. Must match an existing stored value. Example: <code>977e5426-8d13-3824-86aa-b092f8ae52c5</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>service_class_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="service_class_uuid"                data-endpoint="POSTapi-v1-bus-quotes"
               value="d6fa562b-acd5-35ff-babb-d11194d3737b"
               data-component="body">
    <br>
<p>Le champ value doit être un UUID valide. Must match an existing stored value. Example: <code>d6fa562b-acd5-35ff-babb-d11194d3737b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>passenger_count</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="passenger_count"                data-endpoint="POSTapi-v1-bus-quotes"
               value="6"
               data-component="body">
    <br>
<p>Le champ value doit être au moins 1. Le champ value ne doit pas être supérieur à 10. Example: <code>6</code></p>
        </div>
        </form>

                    <h2 id="bus-POSTapi-v1-bus-holds">Hold seats for 15 minutes.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Requires a Sanctum bearer token.</p>

<span id="example-requests-POSTapi-v1-bus-holds">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/bus/holds" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"departure_uuid\": \"6ff8f7f6-1eb3-3525-be4a-3932c805afed\",
    \"origin_stop_uuid\": \"6b72fe4a-5b40-307c-bc24-f79acf9a1bb9\",
    \"destination_stop_uuid\": \"977e5426-8d13-3824-86aa-b092f8ae52c5\",
    \"price_quote_uuid\": \"d6fa562b-acd5-35ff-babb-d11194d3737b\",
    \"seats\": [
        {
            \"seat_uuid\": \"6ff8f7f6-1eb3-3525-be4a-3932c805afed\",
            \"passenger_index\": 16
        }
    ]
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/bus/holds"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "departure_uuid": "6ff8f7f6-1eb3-3525-be4a-3932c805afed",
    "origin_stop_uuid": "6b72fe4a-5b40-307c-bc24-f79acf9a1bb9",
    "destination_stop_uuid": "977e5426-8d13-3824-86aa-b092f8ae52c5",
    "price_quote_uuid": "d6fa562b-acd5-35ff-babb-d11194d3737b",
    "seats": [
        {
            "seat_uuid": "6ff8f7f6-1eb3-3525-be4a-3932c805afed",
            "passenger_index": 16
        }
    ]
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-bus-holds">
            <blockquote>
            <p>Example response (201, Seats held):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;uuid&quot;: &quot;9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb70&quot;,
        &quot;status&quot;: &quot;held&quot;,
        &quot;expires_at&quot;: &quot;2026-08-20T08:15:00Z&quot;,
        &quot;total_amount&quot;: 12500,
        &quot;currency&quot;: &quot;XOF&quot;,
        &quot;channel&quot;: &quot;mobile&quot;,
        &quot;departure_uuid&quot;: &quot;9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e&quot;,
        &quot;segments&quot;: [
            {
                &quot;uuid&quot;: &quot;9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb71&quot;,
                &quot;seat_uuid&quot;: &quot;9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb72&quot;,
                &quot;seat_code&quot;: &quot;4A&quot;,
                &quot;origin_stop&quot;: &quot;Cotonou &middot; St Michel&quot;,
                &quot;destination_stop&quot;: &quot;Malanville&quot;,
                &quot;status&quot;: &quot;held&quot;
            }
        ]
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (409, Seat unavailable):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Le si&egrave;ge 4A n&#039;est plus disponible sur ce segment.&quot;,
    &quot;conflicting_seat_uuid&quot;: &quot;9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb72&quot;,
    &quot;seat_code&quot;: &quot;4A&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-bus-holds" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-bus-holds"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-bus-holds"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-bus-holds" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-bus-holds">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-bus-holds" data-method="POST"
      data-path="api/v1/bus/holds"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-bus-holds', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-bus-holds"
                    onclick="tryItOut('POSTapi-v1-bus-holds');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-bus-holds"
                    onclick="cancelTryOut('POSTapi-v1-bus-holds');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-bus-holds"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/bus/holds</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-bus-holds"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-bus-holds"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-bus-holds"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>departure_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="departure_uuid"                data-endpoint="POSTapi-v1-bus-holds"
               value="6ff8f7f6-1eb3-3525-be4a-3932c805afed"
               data-component="body">
    <br>
<p>Le champ value doit être un UUID valide. Must match an existing stored value. Example: <code>6ff8f7f6-1eb3-3525-be4a-3932c805afed</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>origin_stop_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="origin_stop_uuid"                data-endpoint="POSTapi-v1-bus-holds"
               value="6b72fe4a-5b40-307c-bc24-f79acf9a1bb9"
               data-component="body">
    <br>
<p>Le champ value doit être un UUID valide. Must match an existing stored value. Example: <code>6b72fe4a-5b40-307c-bc24-f79acf9a1bb9</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>destination_stop_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="destination_stop_uuid"                data-endpoint="POSTapi-v1-bus-holds"
               value="977e5426-8d13-3824-86aa-b092f8ae52c5"
               data-component="body">
    <br>
<p>Le champ value doit être un UUID valide. Must match an existing stored value. Example: <code>977e5426-8d13-3824-86aa-b092f8ae52c5</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>price_quote_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="price_quote_uuid"                data-endpoint="POSTapi-v1-bus-holds"
               value="d6fa562b-acd5-35ff-babb-d11194d3737b"
               data-component="body">
    <br>
<p>Le champ value doit être un UUID valide. Must match an existing stored value. Example: <code>d6fa562b-acd5-35ff-babb-d11194d3737b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>seats</code></b>&nbsp;&nbsp;
<small>object[]</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Le champ value doit contenir au moins 1 éléments.</p>
            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>seat_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="seats.0.seat_uuid"                data-endpoint="POSTapi-v1-bus-holds"
               value="6ff8f7f6-1eb3-3525-be4a-3932c805afed"
               data-component="body">
    <br>
<p>Le champ value doit être un UUID valide. Must match an existing stored value. Example: <code>6ff8f7f6-1eb3-3525-be4a-3932c805afed</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>passenger_index</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="seats.0.passenger_index"                data-endpoint="POSTapi-v1-bus-holds"
               value="16"
               data-component="body">
    <br>
<p>Example: <code>16</code></p>
                    </div>
                                    </details>
        </div>
        </form>

                    <h2 id="bus-DELETEapi-v1-bus-holds--reservation_uuid-">Release a seat hold.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Cancels a held reservation and frees its seats immediately, before the
15-minute hold expiry. Only the user who created the hold may release it.
Releasing an already-released or expired hold is a no-op and still
returns 204.</p>
<p>Requires a Sanctum bearer token.</p>

<span id="example-requests-DELETEapi-v1-bus-holds--reservation_uuid-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:8000/api/v1/bus/holds/6ff8f7f6-1eb3-3525-be4a-3932c805afed" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/bus/holds/6ff8f7f6-1eb3-3525-be4a-3932c805afed"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-bus-holds--reservation_uuid-">
            <blockquote>
            <p>Example response (204, Hold released):</p>
        </blockquote>
                <pre>
<code>Empty response</code>
 </pre>
            <blockquote>
            <p>Example response (403, Reservation belongs to another user):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;This action is unauthorized.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Reservation not found):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;No query results for model [App\\Modules\\Booking\\Models\\Reservation].&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-v1-bus-holds--reservation_uuid-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-bus-holds--reservation_uuid-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-bus-holds--reservation_uuid-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-bus-holds--reservation_uuid-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-bus-holds--reservation_uuid-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-bus-holds--reservation_uuid-" data-method="DELETE"
      data-path="api/v1/bus/holds/{reservation_uuid}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-bus-holds--reservation_uuid-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-v1-bus-holds--reservation_uuid-"
                    onclick="tryItOut('DELETEapi-v1-bus-holds--reservation_uuid-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-v1-bus-holds--reservation_uuid-"
                    onclick="cancelTryOut('DELETEapi-v1-bus-holds--reservation_uuid-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-v1-bus-holds--reservation_uuid-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/bus/holds/{reservation_uuid}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-bus-holds--reservation_uuid-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-bus-holds--reservation_uuid-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-bus-holds--reservation_uuid-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>reservation_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="reservation_uuid"                data-endpoint="DELETEapi-v1-bus-holds--reservation_uuid-"
               value="6ff8f7f6-1eb3-3525-be4a-3932c805afed"
               data-component="url">
    <br>
<p>Example: <code>6ff8f7f6-1eb3-3525-be4a-3932c805afed</code></p>
            </div>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>reservation</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="reservation"                data-endpoint="DELETEapi-v1-bus-holds--reservation_uuid-"
               value="9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb70"
               data-component="url">
    <br>
<p>Public UUID of the held reservation. Example: <code>9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb70</code></p>
            </div>
                    </form>

                    <h2 id="bus-POSTapi-v1-bookings--reservation_uuid--passengers">Store passenger details for a reservation.</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-bookings--reservation_uuid--passengers">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/bookings/6ff8f7f6-1eb3-3525-be4a-3932c805afed/passengers" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"passengers\": [
        {
            \"first_name\": \"b\",
            \"last_name\": \"n\",
            \"phone\": \"g\",
            \"email\": \"rowan.gulgowski@example.com\",
            \"document_type\": \"birth_certificate\",
            \"document_number\": \"d\",
            \"nationality\": \"lj\",
            \"birth_date\": \"2026-08-26\",
            \"seat_uuid\": \"c3b6b42e-3a0f-3935-b28d-cb767f8a2a0a\"
        }
    ]
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/bookings/6ff8f7f6-1eb3-3525-be4a-3932c805afed/passengers"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "passengers": [
        {
            "first_name": "b",
            "last_name": "n",
            "phone": "g",
            "email": "rowan.gulgowski@example.com",
            "document_type": "birth_certificate",
            "document_number": "d",
            "nationality": "lj",
            "birth_date": "2026-08-26",
            "seat_uuid": "c3b6b42e-3a0f-3935-b28d-cb767f8a2a0a"
        }
    ]
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-bookings--reservation_uuid--passengers">
            <blockquote>
            <p>Example response (200, Passengers stored):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;uuid&quot;: &quot;9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb70&quot;,
        &quot;status&quot;: &quot;held&quot;,
        &quot;expires_at&quot;: &quot;2026-08-20T08:15:00Z&quot;,
        &quot;total_amount&quot;: 12500,
        &quot;currency&quot;: &quot;XOF&quot;,
        &quot;channel&quot;: &quot;mobile&quot;,
        &quot;departure_uuid&quot;: &quot;9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e&quot;,
        &quot;passengers&quot;: [
            {
                &quot;uuid&quot;: &quot;9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb73&quot;,
                &quot;first_name&quot;: &quot;A&iuml;cha&quot;,
                &quot;last_name&quot;: &quot;HOUNKP&Egrave;&quot;,
                &quot;phone&quot;: &quot;+2290197421860&quot;,
                &quot;document_type&quot;: &quot;passport&quot;,
                &quot;document_number&quot;: &quot;BJ0427851&quot;,
                &quot;nationality&quot;: &quot;BJ&quot;,
                &quot;birth_date&quot;: &quot;1994-04-18&quot;,
                &quot;seat_code&quot;: &quot;4A&quot;
            }
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-bookings--reservation_uuid--passengers" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-bookings--reservation_uuid--passengers"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-bookings--reservation_uuid--passengers"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-bookings--reservation_uuid--passengers" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-bookings--reservation_uuid--passengers">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-bookings--reservation_uuid--passengers" data-method="POST"
      data-path="api/v1/bookings/{reservation_uuid}/passengers"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-bookings--reservation_uuid--passengers', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-bookings--reservation_uuid--passengers"
                    onclick="tryItOut('POSTapi-v1-bookings--reservation_uuid--passengers');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-bookings--reservation_uuid--passengers"
                    onclick="cancelTryOut('POSTapi-v1-bookings--reservation_uuid--passengers');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-bookings--reservation_uuid--passengers"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/bookings/{reservation_uuid}/passengers</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-bookings--reservation_uuid--passengers"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-bookings--reservation_uuid--passengers"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>reservation_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="reservation_uuid"                data-endpoint="POSTapi-v1-bookings--reservation_uuid--passengers"
               value="6ff8f7f6-1eb3-3525-be4a-3932c805afed"
               data-component="url">
    <br>
<p>Example: <code>6ff8f7f6-1eb3-3525-be4a-3932c805afed</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>passengers</code></b>&nbsp;&nbsp;
<small>object[]</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Le champ value doit contenir au moins 1 éléments.</p>
            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>first_name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="passengers.0.first_name"                data-endpoint="POSTapi-v1-bookings--reservation_uuid--passengers"
               value="b"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 120 caractères. Example: <code>b</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>last_name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="passengers.0.last_name"                data-endpoint="POSTapi-v1-bookings--reservation_uuid--passengers"
               value="n"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 120 caractères. Example: <code>n</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>phone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="passengers.0.phone"                data-endpoint="POSTapi-v1-bookings--reservation_uuid--passengers"
               value="g"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 32 caractères. Example: <code>g</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="passengers.0.email"                data-endpoint="POSTapi-v1-bookings--reservation_uuid--passengers"
               value="rowan.gulgowski@example.com"
               data-component="body">
    <br>
<p>Le champ value doit être une adresse e-mail valide. Le champ value ne doit pas dépasser 255 caractères. Example: <code>rowan.gulgowski@example.com</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>document_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="passengers.0.document_type"                data-endpoint="POSTapi-v1-bookings--reservation_uuid--passengers"
               value="birth_certificate"
               data-component="body">
    <br>
<p>Example: <code>birth_certificate</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>national_id</code></li> <li><code>biometric_card</code></li> <li><code>passport</code></li> <li><code>consular_card</code></li> <li><code>cip</code></li></ul>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>document_number</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="passengers.0.document_number"                data-endpoint="POSTapi-v1-bookings--reservation_uuid--passengers"
               value="d"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 64 caractères. Example: <code>d</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>nationality</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="passengers.0.nationality"                data-endpoint="POSTapi-v1-bookings--reservation_uuid--passengers"
               value="lj"
               data-component="body">
    <br>
<p>Le champ value doit contenir 2 caractères. Example: <code>lj</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>birth_date</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="passengers.0.birth_date"                data-endpoint="POSTapi-v1-bookings--reservation_uuid--passengers"
               value="2026-08-26"
               data-component="body">
    <br>
<p>Must be a valid date in the format <code>Y-m-d</code>. Example: <code>2026-08-26</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>seat_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="passengers.0.seat_uuid"                data-endpoint="POSTapi-v1-bookings--reservation_uuid--passengers"
               value="c3b6b42e-3a0f-3935-b28d-cb767f8a2a0a"
               data-component="body">
    <br>
<p>Le champ value doit être un UUID valide. Example: <code>c3b6b42e-3a0f-3935-b28d-cb767f8a2a0a</code></p>
                    </div>
                                    </details>
        </div>
        </form>

                <h1 id="auth">Auth</h1>

    <p>OTP challenge request and verification for registration, login, PIN reset and account unlock.</p>

                                <h2 id="auth-POSTapi-v1-auth-otp-request">POST api/v1/auth/otp/request</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-otp-request">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/auth/otp/request" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"email\": \"gbailey@example.net\",
    \"purpose\": \"pin_reset\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/otp/request"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "email": "gbailey@example.net",
    "purpose": "pin_reset"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-otp-request">
</span>
<span id="execution-results-POSTapi-v1-auth-otp-request" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-otp-request"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-otp-request"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-otp-request" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-otp-request">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-otp-request" data-method="POST"
      data-path="api/v1/auth/otp/request"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-otp-request', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-otp-request"
                    onclick="tryItOut('POSTapi-v1-auth-otp-request');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-otp-request"
                    onclick="cancelTryOut('POSTapi-v1-auth-otp-request');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-otp-request"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/otp/request</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-otp-request"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-otp-request"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-auth-otp-request"
               value="gbailey@example.net"
               data-component="body">
    <br>
<p>Le champ value doit être une adresse e-mail valide. Le champ value ne doit pas dépasser 255 caractères. Example: <code>gbailey@example.net</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>purpose</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="purpose"                data-endpoint="POSTapi-v1-auth-otp-request"
               value="pin_reset"
               data-component="body">
    <br>
<p>Example: <code>pin_reset</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>registration</code></li> <li><code>login_new_device</code></li> <li><code>pin_reset</code></li> <li><code>account_unlock</code></li></ul>
        </div>
        </form>

                    <h2 id="auth-POSTapi-v1-auth-otp-verify">POST api/v1/auth/otp/verify</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-otp-verify">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/auth/otp/verify" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"otp_challenge_uuid\": \"6ff8f7f6-1eb3-3525-be4a-3932c805afed\",
    \"purpose\": \"account_unlock\",
    \"code\": \"569775\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/otp/verify"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "otp_challenge_uuid": "6ff8f7f6-1eb3-3525-be4a-3932c805afed",
    "purpose": "account_unlock",
    "code": "569775"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-otp-verify">
</span>
<span id="execution-results-POSTapi-v1-auth-otp-verify" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-otp-verify"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-otp-verify"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-otp-verify" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-otp-verify">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-otp-verify" data-method="POST"
      data-path="api/v1/auth/otp/verify"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-otp-verify', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-otp-verify"
                    onclick="tryItOut('POSTapi-v1-auth-otp-verify');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-otp-verify"
                    onclick="cancelTryOut('POSTapi-v1-auth-otp-verify');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-otp-verify"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/otp/verify</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-otp-verify"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-otp-verify"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>otp_challenge_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="otp_challenge_uuid"                data-endpoint="POSTapi-v1-auth-otp-verify"
               value="6ff8f7f6-1eb3-3525-be4a-3932c805afed"
               data-component="body">
    <br>
<p>Le champ value doit être un UUID valide. Example: <code>6ff8f7f6-1eb3-3525-be4a-3932c805afed</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>purpose</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="purpose"                data-endpoint="POSTapi-v1-auth-otp-verify"
               value="account_unlock"
               data-component="body">
    <br>
<p>Example: <code>account_unlock</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>registration</code></li> <li><code>login_new_device</code></li> <li><code>pin_reset</code></li> <li><code>account_unlock</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>code</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="code"                data-endpoint="POSTapi-v1-auth-otp-verify"
               value="569775"
               data-component="body">
    <br>
<p>Le champ value doit contenir 6 chiffres. Example: <code>569775</code></p>
        </div>
        </form>

                    <h2 id="auth-POSTapi-v1-auth-pin-reset">POST api/v1/auth/pin/reset</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-pin-reset">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/auth/pin/reset" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"otp_challenge_uuid\": \"6ff8f7f6-1eb3-3525-be4a-3932c805afed\",
    \"verification_token\": \"gzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtn\",
    \"pin\": \"9775\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/pin/reset"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "otp_challenge_uuid": "6ff8f7f6-1eb3-3525-be4a-3932c805afed",
    "verification_token": "gzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtn",
    "pin": "9775"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-pin-reset">
</span>
<span id="execution-results-POSTapi-v1-auth-pin-reset" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-pin-reset"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-pin-reset"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-pin-reset" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-pin-reset">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-pin-reset" data-method="POST"
      data-path="api/v1/auth/pin/reset"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-pin-reset', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-pin-reset"
                    onclick="tryItOut('POSTapi-v1-auth-pin-reset');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-pin-reset"
                    onclick="cancelTryOut('POSTapi-v1-auth-pin-reset');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-pin-reset"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/pin/reset</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-pin-reset"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-pin-reset"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>otp_challenge_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="otp_challenge_uuid"                data-endpoint="POSTapi-v1-auth-pin-reset"
               value="6ff8f7f6-1eb3-3525-be4a-3932c805afed"
               data-component="body">
    <br>
<p>Le champ value doit être un UUID valide. Example: <code>6ff8f7f6-1eb3-3525-be4a-3932c805afed</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>verification_token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="verification_token"                data-endpoint="POSTapi-v1-auth-pin-reset"
               value="gzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtn"
               data-component="body">
    <br>
<p>Le champ value doit contenir 64 caractères. Example: <code>gzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtn</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>pin</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="pin"                data-endpoint="POSTapi-v1-auth-pin-reset"
               value="9775"
               data-component="body">
    <br>
<p>Le champ value doit contenir 4 chiffres. Example: <code>9775</code></p>
        </div>
        </form>

                    <h2 id="auth-POSTapi-v1-auth-unlock">POST api/v1/auth/unlock</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-unlock">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/auth/unlock" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"otp_challenge_uuid\": \"6ff8f7f6-1eb3-3525-be4a-3932c805afed\",
    \"verification_token\": \"gzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtn\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/unlock"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "otp_challenge_uuid": "6ff8f7f6-1eb3-3525-be4a-3932c805afed",
    "verification_token": "gzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtn"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-unlock">
</span>
<span id="execution-results-POSTapi-v1-auth-unlock" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-unlock"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-unlock"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-unlock" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-unlock">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-unlock" data-method="POST"
      data-path="api/v1/auth/unlock"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-unlock', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-unlock"
                    onclick="tryItOut('POSTapi-v1-auth-unlock');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-unlock"
                    onclick="cancelTryOut('POSTapi-v1-auth-unlock');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-unlock"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/unlock</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-unlock"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-unlock"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>otp_challenge_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="otp_challenge_uuid"                data-endpoint="POSTapi-v1-auth-unlock"
               value="6ff8f7f6-1eb3-3525-be4a-3932c805afed"
               data-component="body">
    <br>
<p>Le champ value doit être un UUID valide. Example: <code>6ff8f7f6-1eb3-3525-be4a-3932c805afed</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>verification_token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="verification_token"                data-endpoint="POSTapi-v1-auth-unlock"
               value="gzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtn"
               data-component="body">
    <br>
<p>Le champ value doit contenir 64 caractères. Example: <code>gzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtn</code></p>
        </div>
        </form>

                    <h2 id="auth-POSTapi-v1-auth-register-complete">POST api/v1/auth/register/complete</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-register-complete">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/auth/register/complete" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"otp_challenge_uuid\": \"6ff8f7f6-1eb3-3525-be4a-3932c805afed\",
    \"verification_token\": \"gzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtn\",
    \"pin\": \"9775\",
    \"device_name\": \"n\",
    \"platform\": \"android\",
    \"device_fingerprint\": \"g\",
    \"push_token\": \"z\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/register/complete"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "otp_challenge_uuid": "6ff8f7f6-1eb3-3525-be4a-3932c805afed",
    "verification_token": "gzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtn",
    "pin": "9775",
    "device_name": "n",
    "platform": "android",
    "device_fingerprint": "g",
    "push_token": "z"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-register-complete">
</span>
<span id="execution-results-POSTapi-v1-auth-register-complete" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-register-complete"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-register-complete"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-register-complete" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-register-complete">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-register-complete" data-method="POST"
      data-path="api/v1/auth/register/complete"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-register-complete', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-register-complete"
                    onclick="tryItOut('POSTapi-v1-auth-register-complete');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-register-complete"
                    onclick="cancelTryOut('POSTapi-v1-auth-register-complete');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-register-complete"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/register/complete</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-register-complete"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-register-complete"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>otp_challenge_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="otp_challenge_uuid"                data-endpoint="POSTapi-v1-auth-register-complete"
               value="6ff8f7f6-1eb3-3525-be4a-3932c805afed"
               data-component="body">
    <br>
<p>Le champ value doit être un UUID valide. Example: <code>6ff8f7f6-1eb3-3525-be4a-3932c805afed</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>verification_token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="verification_token"                data-endpoint="POSTapi-v1-auth-register-complete"
               value="gzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtn"
               data-component="body">
    <br>
<p>Le champ value doit contenir 64 caractères. Example: <code>gzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtn</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>pin</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="pin"                data-endpoint="POSTapi-v1-auth-register-complete"
               value="9775"
               data-component="body">
    <br>
<p>Le champ value doit contenir 4 chiffres. Example: <code>9775</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>device_name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="device_name"                data-endpoint="POSTapi-v1-auth-register-complete"
               value="n"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 120 caractères. Example: <code>n</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>platform</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="platform"                data-endpoint="POSTapi-v1-auth-register-complete"
               value="android"
               data-component="body">
    <br>
<p>Example: <code>android</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>android</code></li> <li><code>ios</code></li> <li><code>web</code></li> <li><code>unknown</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>device_fingerprint</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="device_fingerprint"                data-endpoint="POSTapi-v1-auth-register-complete"
               value="g"
               data-component="body">
    <br>
<p>Le champ value doit contenir au moins 16 caractères. Le champ value ne doit pas dépasser 255 caractères. Example: <code>g</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>push_token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="push_token"                data-endpoint="POSTapi-v1-auth-register-complete"
               value="z"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 512 caractères. Example: <code>z</code></p>
        </div>
        </form>

                    <h2 id="auth-POSTapi-v1-auth-login">POST api/v1/auth/login</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-login">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/auth/login" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"email\": \"gbailey@example.net\",
    \"pin\": \"9775\",
    \"device_name\": \"n\",
    \"platform\": \"android\",
    \"device_fingerprint\": \"g\",
    \"push_token\": \"z\",
    \"otp_challenge_uuid\": \"977e5426-8d13-3824-86aa-b092f8ae52c5\",
    \"verification_token\": \"yvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtnnoqi\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/login"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "email": "gbailey@example.net",
    "pin": "9775",
    "device_name": "n",
    "platform": "android",
    "device_fingerprint": "g",
    "push_token": "z",
    "otp_challenge_uuid": "977e5426-8d13-3824-86aa-b092f8ae52c5",
    "verification_token": "yvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtnnoqi"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-login">
</span>
<span id="execution-results-POSTapi-v1-auth-login" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-login"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-login"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-login" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-login">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-login" data-method="POST"
      data-path="api/v1/auth/login"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-login', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-login"
                    onclick="tryItOut('POSTapi-v1-auth-login');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-login"
                    onclick="cancelTryOut('POSTapi-v1-auth-login');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-login"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/login</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-login"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-login"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-auth-login"
               value="gbailey@example.net"
               data-component="body">
    <br>
<p>Le champ value doit être une adresse e-mail valide. Le champ value ne doit pas dépasser 255 caractères. Example: <code>gbailey@example.net</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>pin</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="pin"                data-endpoint="POSTapi-v1-auth-login"
               value="9775"
               data-component="body">
    <br>
<p>Le champ value doit contenir 4 chiffres. Example: <code>9775</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>device_name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="device_name"                data-endpoint="POSTapi-v1-auth-login"
               value="n"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 120 caractères. Example: <code>n</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>platform</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="platform"                data-endpoint="POSTapi-v1-auth-login"
               value="android"
               data-component="body">
    <br>
<p>Example: <code>android</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>android</code></li> <li><code>ios</code></li> <li><code>web</code></li> <li><code>unknown</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>device_fingerprint</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="device_fingerprint"                data-endpoint="POSTapi-v1-auth-login"
               value="g"
               data-component="body">
    <br>
<p>Le champ value doit contenir au moins 16 caractères. Le champ value ne doit pas dépasser 255 caractères. Example: <code>g</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>push_token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="push_token"                data-endpoint="POSTapi-v1-auth-login"
               value="z"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 512 caractères. Example: <code>z</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>otp_challenge_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="otp_challenge_uuid"                data-endpoint="POSTapi-v1-auth-login"
               value="977e5426-8d13-3824-86aa-b092f8ae52c5"
               data-component="body">
    <br>
<p>This field is required when <code>verification_token</code> is present. Le champ value doit être un UUID valide. Example: <code>977e5426-8d13-3824-86aa-b092f8ae52c5</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>verification_token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="verification_token"                data-endpoint="POSTapi-v1-auth-login"
               value="yvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtnnoqi"
               data-component="body">
    <br>
<p>This field is required when <code>otp_challenge_uuid</code> is present. Le champ value doit contenir 64 caractères. Example: <code>yvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwtujwvlxjklqppwqbewtnnoqi</code></p>
        </div>
        </form>

                    <h2 id="auth-POSTapi-v1-auth-oauth-token">Échange un jeton OAuth provider contre une session KORA.</h2>

<p>
</p>

<p>Le client mobile authentifie l'utilisateur via le SDK natif du
provider (Google Sign-In, Facebook SDK, TikTok Login Kit), récupère
un access token, puis l'envoie à cet endpoint. Le backend valide
le jeton via Socialite, retrouve ou crée le compte KORA, lie le
compte social, télécharge l'avatar via Spatie Media Library et
émet un token Sanctum.</p>

<span id="example-requests-POSTapi-v1-auth-oauth-token">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/auth/oauth/token" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"provider\": \"google\",
    \"provider_token\": \"b\",
    \"device_name\": \"n\",
    \"platform\": \"android\",
    \"device_fingerprint\": \"g\",
    \"push_token\": \"z\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/oauth/token"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "provider": "google",
    "provider_token": "b",
    "device_name": "n",
    "platform": "android",
    "device_fingerprint": "g",
    "push_token": "z"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-oauth-token">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;token&quot;: &quot;3|aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789abcdef&quot;,
        &quot;token_type&quot;: &quot;Bearer&quot;,
        &quot;expires_at&quot;: &quot;2026-08-27T13:05:00.000Z&quot;,
        &quot;user&quot;: {
            &quot;uuid&quot;: &quot;977e5426-8d13-3824-86aa-b092f8ae52c5&quot;,
            &quot;name&quot;: &quot;A&iuml;cha Hounkp&egrave;&quot;,
            &quot;email&quot;: &quot;aicha.hounkpe@gmail.com&quot;,
            &quot;phone&quot;: null,
            &quot;country_code&quot;: null,
            &quot;birth_date&quot;: null,
            &quot;avatar_url&quot;: &quot;https://api.korabus.app/media/avatars/123/thumb.jpg&quot;,
            &quot;status&quot;: &quot;active&quot;,
            &quot;permissions&quot;: [
                &quot;bookings.create&quot;,
                &quot;bookings.view-own&quot;,
                &quot;tickets.view&quot;
            ]
        },
        &quot;device&quot;: {
            &quot;uuid&quot;: &quot;a3091549-9179-454e-8440-58179f2208df&quot;,
            &quot;name&quot;: &quot;iPhone 15 Pro&quot;,
            &quot;platform&quot;: &quot;ios&quot;,
            &quot;trusted_at&quot;: &quot;2026-08-20T13:05:00.000Z&quot;,
            &quot;last_seen_at&quot;: &quot;2026-08-20T13:05:00.000Z&quot;,
            &quot;status&quot;: &quot;trusted&quot;
        }
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;token&quot;: &quot;1|aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789abcdef&quot;,
        &quot;token_type&quot;: &quot;Bearer&quot;,
        &quot;expires_at&quot;: &quot;2026-08-27T13:05:00.000Z&quot;,
        &quot;user&quot;: {
            &quot;uuid&quot;: &quot;977e5426-8d13-3824-86aa-b092f8ae52c5&quot;,
            &quot;name&quot;: &quot;A&iuml;cha Hounkp&egrave;&quot;,
            &quot;email&quot;: &quot;aicha.hounkpe@gmail.com&quot;,
            &quot;phone&quot;: null,
            &quot;country_code&quot;: null,
            &quot;birth_date&quot;: null,
            &quot;avatar_url&quot;: &quot;https://api.korabus.app/media/avatars/124/thumb.jpg&quot;,
            &quot;status&quot;: &quot;active&quot;,
            &quot;permissions&quot;: [
                &quot;bookings.create&quot;,
                &quot;bookings.view-own&quot;,
                &quot;tickets.view&quot;
            ]
        },
        &quot;device&quot;: {
            &quot;uuid&quot;: &quot;a3091549-9179-454e-8440-58179f2208df&quot;,
            &quot;name&quot;: &quot;iPhone 15 Pro&quot;,
            &quot;platform&quot;: &quot;ios&quot;,
            &quot;trusted_at&quot;: &quot;2026-08-20T13:05:00.000Z&quot;,
            &quot;last_seen_at&quot;: &quot;2026-08-20T13:05:00.000Z&quot;,
            &quot;status&quot;: &quot;trusted&quot;
        }
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (403):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: {
        &quot;code&quot;: &quot;account_suspended&quot;,
        &quot;message&quot;: &quot;Le compte est suspendu. Contactez le support.&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (422):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: {
        &quot;code&quot;: &quot;invalid_oauth_token&quot;,
        &quot;message&quot;: &quot;Le jeton OAuth fourni n&#039;est pas valide ou a expir&eacute;.&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (422):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: {
        &quot;code&quot;: &quot;email_required&quot;,
        &quot;message&quot;: &quot;Le fournisseur OAuth n&#039;a pas retourn&eacute; d&#039;adresse email. Un email est obligatoire pour cr&eacute;er un compte KORA.&quot;
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-auth-oauth-token" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-oauth-token"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-oauth-token"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-oauth-token" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-oauth-token">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-oauth-token" data-method="POST"
      data-path="api/v1/auth/oauth/token"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-oauth-token', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-oauth-token"
                    onclick="tryItOut('POSTapi-v1-auth-oauth-token');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-oauth-token"
                    onclick="cancelTryOut('POSTapi-v1-auth-oauth-token');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-oauth-token"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/oauth/token</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-oauth-token"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-oauth-token"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>provider</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="provider"                data-endpoint="POSTapi-v1-auth-oauth-token"
               value="google"
               data-component="body">
    <br>
<p>Example: <code>google</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>google</code></li> <li><code>facebook</code></li> <li><code>tiktok</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>provider_token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="provider_token"                data-endpoint="POSTapi-v1-auth-oauth-token"
               value="b"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 4096 caractères. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>device_name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="device_name"                data-endpoint="POSTapi-v1-auth-oauth-token"
               value="n"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 120 caractères. Example: <code>n</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>platform</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="platform"                data-endpoint="POSTapi-v1-auth-oauth-token"
               value="android"
               data-component="body">
    <br>
<p>Example: <code>android</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>android</code></li> <li><code>ios</code></li> <li><code>web</code></li> <li><code>unknown</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>device_fingerprint</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="device_fingerprint"                data-endpoint="POSTapi-v1-auth-oauth-token"
               value="g"
               data-component="body">
    <br>
<p>Le champ value doit contenir au moins 16 caractères. Le champ value ne doit pas dépasser 255 caractères. Example: <code>g</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>push_token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="push_token"                data-endpoint="POSTapi-v1-auth-oauth-token"
               value="z"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 512 caractères. Example: <code>z</code></p>
        </div>
        </form>

                    <h2 id="auth-GETapi-v1-auth-me">GET api/v1/auth/me</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-auth-me">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/auth/me" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/me"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-auth-me">
    </span>
<span id="execution-results-GETapi-v1-auth-me" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-auth-me"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-auth-me"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-auth-me" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-auth-me">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-auth-me" data-method="GET"
      data-path="api/v1/auth/me"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-auth-me', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-auth-me"
                    onclick="tryItOut('GETapi-v1-auth-me');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-auth-me"
                    onclick="cancelTryOut('GETapi-v1-auth-me');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-auth-me"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/auth/me</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-auth-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-auth-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="auth-PUTapi-v1-auth-me">PUT api/v1/auth/me</h2>

<p>
</p>



<span id="example-requests-PUTapi-v1-auth-me">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:8000/api/v1/auth/me" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"b\",
    \"email\": \"zbailey@example.net\",
    \"phone\": \"iyvdljnikhwaykcm\",
    \"country_code\": \"yuwp\",
    \"birth_date\": \"2022-09-19\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/me"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "b",
    "email": "zbailey@example.net",
    "phone": "iyvdljnikhwaykcm",
    "country_code": "yuwp",
    "birth_date": "2022-09-19"
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-auth-me">
</span>
<span id="execution-results-PUTapi-v1-auth-me" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-auth-me"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-auth-me"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-auth-me" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-auth-me">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-auth-me" data-method="PUT"
      data-path="api/v1/auth/me"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-auth-me', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-v1-auth-me"
                    onclick="tryItOut('PUTapi-v1-auth-me');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-v1-auth-me"
                    onclick="cancelTryOut('PUTapi-v1-auth-me');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-v1-auth-me"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/auth/me</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-auth-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-auth-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="PUTapi-v1-auth-me"
               value="b"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 120 caractères. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="PUTapi-v1-auth-me"
               value="zbailey@example.net"
               data-component="body">
    <br>
<p>Le champ value doit être une adresse e-mail valide. Le champ value ne doit pas dépasser 255 caractères. Example: <code>zbailey@example.net</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>phone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="phone"                data-endpoint="PUTapi-v1-auth-me"
               value="iyvdljnikhwaykcm"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 20 caractères. Example: <code>iyvdljnikhwaykcm</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>country_code</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="country_code"                data-endpoint="PUTapi-v1-auth-me"
               value="yuwp"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 4 caractères. Example: <code>yuwp</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>birth_date</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="birth_date"                data-endpoint="PUTapi-v1-auth-me"
               value="2022-09-19"
               data-component="body">
    <br>
<p>Le champ value doit être une date valide. Le champ value doit être une date antérieure à <code>today</code>. Example: <code>2022-09-19</code></p>
        </div>
        </form>

                    <h2 id="auth-POSTapi-v1-auth-pin-change">POST api/v1/auth/pin/change</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-pin-change">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/auth/pin/change" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"current_pin\": \"9775\",
    \"new_pin\": \"9775\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/pin/change"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "current_pin": "9775",
    "new_pin": "9775"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-pin-change">
</span>
<span id="execution-results-POSTapi-v1-auth-pin-change" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-pin-change"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-pin-change"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-pin-change" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-pin-change">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-pin-change" data-method="POST"
      data-path="api/v1/auth/pin/change"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-pin-change', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-pin-change"
                    onclick="tryItOut('POSTapi-v1-auth-pin-change');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-pin-change"
                    onclick="cancelTryOut('POSTapi-v1-auth-pin-change');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-pin-change"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/pin/change</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-pin-change"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-pin-change"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>current_pin</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="current_pin"                data-endpoint="POSTapi-v1-auth-pin-change"
               value="9775"
               data-component="body">
    <br>
<p>Le champ value doit contenir 4 chiffres. Example: <code>9775</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>new_pin</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="new_pin"                data-endpoint="POSTapi-v1-auth-pin-change"
               value="9775"
               data-component="body">
    <br>
<p>Le champ value doit contenir 4 chiffres. Must not be one of <code></code>. Example: <code>9775</code></p>
        </div>
        </form>

                    <h2 id="auth-GETapi-v1-auth-preferences">GET api/v1/auth/preferences</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-auth-preferences">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/auth/preferences" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/preferences"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-auth-preferences">
    </span>
<span id="execution-results-GETapi-v1-auth-preferences" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-auth-preferences"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-auth-preferences"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-auth-preferences" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-auth-preferences">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-auth-preferences" data-method="GET"
      data-path="api/v1/auth/preferences"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-auth-preferences', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-auth-preferences"
                    onclick="tryItOut('GETapi-v1-auth-preferences');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-auth-preferences"
                    onclick="cancelTryOut('GETapi-v1-auth-preferences');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-auth-preferences"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/auth/preferences</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-auth-preferences"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-auth-preferences"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="auth-PUTapi-v1-auth-preferences">PUT api/v1/auth/preferences</h2>

<p>
</p>



<span id="example-requests-PUTapi-v1-auth-preferences">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:8000/api/v1/auth/preferences" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"push_notifications\": false,
    \"whatsapp_notifications\": true,
    \"email_notifications\": false,
    \"locale\": \"fr\",
    \"theme\": \"system\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/preferences"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "push_notifications": false,
    "whatsapp_notifications": true,
    "email_notifications": false,
    "locale": "fr",
    "theme": "system"
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-auth-preferences">
</span>
<span id="execution-results-PUTapi-v1-auth-preferences" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-auth-preferences"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-auth-preferences"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-auth-preferences" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-auth-preferences">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-auth-preferences" data-method="PUT"
      data-path="api/v1/auth/preferences"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-auth-preferences', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-v1-auth-preferences"
                    onclick="tryItOut('PUTapi-v1-auth-preferences');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-v1-auth-preferences"
                    onclick="cancelTryOut('PUTapi-v1-auth-preferences');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-v1-auth-preferences"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/auth/preferences</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-auth-preferences"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-auth-preferences"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>push_notifications</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PUTapi-v1-auth-preferences" style="display: none">
            <input type="radio" name="push_notifications"
                   value="true"
                   data-endpoint="PUTapi-v1-auth-preferences"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PUTapi-v1-auth-preferences" style="display: none">
            <input type="radio" name="push_notifications"
                   value="false"
                   data-endpoint="PUTapi-v1-auth-preferences"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Example: <code>true</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>whatsapp_notifications</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PUTapi-v1-auth-preferences" style="display: none">
            <input type="radio" name="whatsapp_notifications"
                   value="true"
                   data-endpoint="PUTapi-v1-auth-preferences"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PUTapi-v1-auth-preferences" style="display: none">
            <input type="radio" name="whatsapp_notifications"
                   value="false"
                   data-endpoint="PUTapi-v1-auth-preferences"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Example: <code>true</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email_notifications</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PUTapi-v1-auth-preferences" style="display: none">
            <input type="radio" name="email_notifications"
                   value="true"
                   data-endpoint="PUTapi-v1-auth-preferences"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PUTapi-v1-auth-preferences" style="display: none">
            <input type="radio" name="email_notifications"
                   value="false"
                   data-endpoint="PUTapi-v1-auth-preferences"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Example: <code>false</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>locale</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="locale"                data-endpoint="PUTapi-v1-auth-preferences"
               value="fr"
               data-component="body">
    <br>
<p>Example: <code>fr</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>fr</code></li> <li><code>en</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>theme</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="theme"                data-endpoint="PUTapi-v1-auth-preferences"
               value="system"
               data-component="body">
    <br>
<p>Example: <code>system</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>light</code></li> <li><code>dark</code></li> <li><code>system</code></li></ul>
        </div>
        </form>

                    <h2 id="auth-GETapi-v1-auth-devices">GET api/v1/auth/devices</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-auth-devices">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/auth/devices" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/devices"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-auth-devices">
    </span>
<span id="execution-results-GETapi-v1-auth-devices" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-auth-devices"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-auth-devices"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-auth-devices" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-auth-devices">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-auth-devices" data-method="GET"
      data-path="api/v1/auth/devices"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-auth-devices', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-auth-devices"
                    onclick="tryItOut('GETapi-v1-auth-devices');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-auth-devices"
                    onclick="cancelTryOut('GETapi-v1-auth-devices');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-auth-devices"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/auth/devices</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-auth-devices"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-auth-devices"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="auth-DELETEapi-v1-auth-devices--uuid-">DELETE api/v1/auth/devices/{uuid}</h2>

<p>
</p>



<span id="example-requests-DELETEapi-v1-auth-devices--uuid-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:8000/api/v1/auth/devices/6ff8f7f6-1eb3-3525-be4a-3932c805afed" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/devices/6ff8f7f6-1eb3-3525-be4a-3932c805afed"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-auth-devices--uuid-">
</span>
<span id="execution-results-DELETEapi-v1-auth-devices--uuid-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-auth-devices--uuid-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-auth-devices--uuid-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-auth-devices--uuid-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-auth-devices--uuid-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-auth-devices--uuid-" data-method="DELETE"
      data-path="api/v1/auth/devices/{uuid}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-auth-devices--uuid-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-v1-auth-devices--uuid-"
                    onclick="tryItOut('DELETEapi-v1-auth-devices--uuid-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-v1-auth-devices--uuid-"
                    onclick="cancelTryOut('DELETEapi-v1-auth-devices--uuid-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-v1-auth-devices--uuid-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/auth/devices/{uuid}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-auth-devices--uuid-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-auth-devices--uuid-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="uuid"                data-endpoint="DELETEapi-v1-auth-devices--uuid-"
               value="6ff8f7f6-1eb3-3525-be4a-3932c805afed"
               data-component="url">
    <br>
<p>Example: <code>6ff8f7f6-1eb3-3525-be4a-3932c805afed</code></p>
            </div>
                    </form>

                    <h2 id="auth-POSTapi-v1-auth-logout">POST api/v1/auth/logout</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-logout">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/auth/logout" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/logout"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-logout">
</span>
<span id="execution-results-POSTapi-v1-auth-logout" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-logout"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-logout"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-logout" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-logout">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-logout" data-method="POST"
      data-path="api/v1/auth/logout"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-logout', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-logout"
                    onclick="tryItOut('POSTapi-v1-auth-logout');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-logout"
                    onclick="cancelTryOut('POSTapi-v1-auth-logout');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-logout"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/logout</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-logout"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-logout"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                <h1 id="endpoints">Endpoints</h1>

    

                                <h2 id="endpoints-GETapi-v1-bookings--reservation_uuid--tickets">GET api/v1/bookings/{reservation_uuid}/tickets</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-bookings--reservation_uuid--tickets">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://kora-ops-web.local/api/v1/bookings/6ff8f7f6-1eb3-3525-be4a-3932c805afed/tickets" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://kora-ops-web.local/api/v1/bookings/6ff8f7f6-1eb3-3525-be4a-3932c805afed/tickets"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-bookings--reservation_uuid--tickets">
    </span>
<span id="execution-results-GETapi-v1-bookings--reservation_uuid--tickets" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-bookings--reservation_uuid--tickets"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-bookings--reservation_uuid--tickets"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-bookings--reservation_uuid--tickets" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-bookings--reservation_uuid--tickets">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-bookings--reservation_uuid--tickets" data-method="GET"
      data-path="api/v1/bookings/{reservation_uuid}/tickets"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-bookings--reservation_uuid--tickets', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-bookings--reservation_uuid--tickets"
                    onclick="tryItOut('GETapi-v1-bookings--reservation_uuid--tickets');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-bookings--reservation_uuid--tickets"
                    onclick="cancelTryOut('GETapi-v1-bookings--reservation_uuid--tickets');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-bookings--reservation_uuid--tickets"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/bookings/{reservation_uuid}/tickets</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-bookings--reservation_uuid--tickets"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-bookings--reservation_uuid--tickets"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>reservation_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="reservation_uuid"                data-endpoint="GETapi-v1-bookings--reservation_uuid--tickets"
               value="6ff8f7f6-1eb3-3525-be4a-3932c805afed"
               data-component="url">
    <br>
<p>Example: <code>6ff8f7f6-1eb3-3525-be4a-3932c805afed</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-GETapi-v1-tickets--uuid-">GET api/v1/tickets/{uuid}</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-tickets--uuid-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://kora-ops-web.local/api/v1/tickets/6ff8f7f6-1eb3-3525-be4a-3932c805afed" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://kora-ops-web.local/api/v1/tickets/6ff8f7f6-1eb3-3525-be4a-3932c805afed"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-tickets--uuid-">
    </span>
<span id="execution-results-GETapi-v1-tickets--uuid-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-tickets--uuid-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-tickets--uuid-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-tickets--uuid-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-tickets--uuid-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-tickets--uuid-" data-method="GET"
      data-path="api/v1/tickets/{uuid}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-tickets--uuid-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-tickets--uuid-"
                    onclick="tryItOut('GETapi-v1-tickets--uuid-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-tickets--uuid-"
                    onclick="cancelTryOut('GETapi-v1-tickets--uuid-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-tickets--uuid-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/tickets/{uuid}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-tickets--uuid-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-tickets--uuid-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="uuid"                data-endpoint="GETapi-v1-tickets--uuid-"
               value="6ff8f7f6-1eb3-3525-be4a-3932c805afed"
               data-component="url">
    <br>
<p>Example: <code>6ff8f7f6-1eb3-3525-be4a-3932c805afed</code></p>
            </div>
                    </form>

                <h1 id="health">Health</h1>

    <p>Application health and liveness probes for load balancers and monitoring.</p>

                                <h2 id="health-GETapi-v1-health">Full application health: database, cache (Redis), and API version.</h2>

<p>
</p>

<p>Returns 200 when every dependency is reachable, 503 otherwise so load
balancers and monitoring can drain traffic from an unhealthy instance.</p>

<span id="example-requests-GETapi-v1-health">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/health" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/health"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-health">
    </span>
<span id="execution-results-GETapi-v1-health" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-health"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-health"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-health" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-health">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-health" data-method="GET"
      data-path="api/v1/health"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-health', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-health"
                    onclick="tryItOut('GETapi-v1-health');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-health"
                    onclick="cancelTryOut('GETapi-v1-health');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-health"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/health</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-health"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-health"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="health-GETapi-v1-health-ping">Lightweight liveness probe for load balancers: always 200 when the
process can answer, with no dependency checks.</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-health-ping">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/health/ping" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/health/ping"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-health-ping">
    </span>
<span id="execution-results-GETapi-v1-health-ping" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-health-ping"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-health-ping"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-health-ping" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-health-ping">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-health-ping" data-method="GET"
      data-path="api/v1/health/ping"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-health-ping', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-health-ping"
                    onclick="tryItOut('GETapi-v1-health-ping');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-health-ping"
                    onclick="cancelTryOut('GETapi-v1-health-ping');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-health-ping"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/health/ping</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-health-ping"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-health-ping"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                <h1 id="payments">Payments</h1>

    <p>Payment lifecycle endpoints: initiation, polling status, and provider webhook confirmation.</p>

                                <h2 id="payments-GETapi-v1-payments-methods">Return the active payment methods and providers configured for the mobile app.</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-payments-methods">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://kora-ops-web.local/api/v1/payments/methods" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://kora-ops-web.local/api/v1/payments/methods"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-payments-methods">
    </span>
<span id="execution-results-GETapi-v1-payments-methods" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-payments-methods"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-payments-methods"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-payments-methods" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-payments-methods">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-payments-methods" data-method="GET"
      data-path="api/v1/payments/methods"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-payments-methods', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-payments-methods"
                    onclick="tryItOut('GETapi-v1-payments-methods');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-payments-methods"
                    onclick="cancelTryOut('GETapi-v1-payments-methods');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-payments-methods"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/payments/methods</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-payments-methods"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-payments-methods"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="payments-POSTapi-v1-payments-initiate">Initiate a payment for a held reservation.</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-payments-initiate">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/payments/initiate" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"reservation_uuid\": \"6ff8f7f6-1eb3-3525-be4a-3932c805afed\",
    \"method\": \"wallet\",
    \"provider\": \"g\",
    \"idempotency_key\": \"z\",
    \"phone\": \"m\",
    \"mobile_money_network\": \"celtiis_cash\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/payments/initiate"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "reservation_uuid": "6ff8f7f6-1eb3-3525-be4a-3932c805afed",
    "method": "wallet",
    "provider": "g",
    "idempotency_key": "z",
    "phone": "m",
    "mobile_money_network": "celtiis_cash"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-payments-initiate">
            <blockquote>
            <p>Example response (201, Payment initiated):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;uuid&quot;: &quot;9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb80&quot;,
        &quot;status&quot;: &quot;pending&quot;,
        &quot;amount&quot;: 12500,
        &quot;currency&quot;: &quot;XOF&quot;,
        &quot;provider&quot;: &quot;mtn_momo&quot;,
        &quot;provider_reference&quot;: &quot;MM-98270341&quot;,
        &quot;expires_at&quot;: &quot;2026-08-20T08:15:00Z&quot;,
        &quot;polling_interval_seconds&quot;: 10
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-payments-initiate" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-payments-initiate"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-payments-initiate"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-payments-initiate" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-payments-initiate">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-payments-initiate" data-method="POST"
      data-path="api/v1/payments/initiate"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-payments-initiate', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-payments-initiate"
                    onclick="tryItOut('POSTapi-v1-payments-initiate');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-payments-initiate"
                    onclick="cancelTryOut('POSTapi-v1-payments-initiate');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-payments-initiate"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/payments/initiate</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-payments-initiate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-payments-initiate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>reservation_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="reservation_uuid"                data-endpoint="POSTapi-v1-payments-initiate"
               value="6ff8f7f6-1eb3-3525-be4a-3932c805afed"
               data-component="body">
    <br>
<p>Le champ value doit être un UUID valide. Must match an existing stored value. Example: <code>6ff8f7f6-1eb3-3525-be4a-3932c805afed</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>method</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="method"                data-endpoint="POSTapi-v1-payments-initiate"
               value="wallet"
               data-component="body">
    <br>
<p>Example: <code>wallet</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>mobile_money</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>provider</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="provider"                data-endpoint="POSTapi-v1-payments-initiate"
               value="g"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 64 caractères. Example: <code>g</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>idempotency_key</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="idempotency_key"                data-endpoint="POSTapi-v1-payments-initiate"
               value="z"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 255 caractères. Example: <code>z</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>phone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="phone"                data-endpoint="POSTapi-v1-payments-initiate"
               value="m"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 32 caractères. Example: <code>m</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>mobile_money_network</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="mobile_money_network"                data-endpoint="POSTapi-v1-payments-initiate"
               value="celtiis_cash"
               data-component="body">
    <br>
<p>Example: <code>celtiis_cash</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>mtn_momo</code></li> <li><code>moov_flooz</code></li> <li><code>celtiis_cash</code></li></ul>
        </div>
        </form>

                    <h2 id="payments-GETapi-v1-payments--uuid-">Get payment status (polling).</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-payments--uuid-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/payments/6ff8f7f6-1eb3-3525-be4a-3932c805afed" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/payments/6ff8f7f6-1eb3-3525-be4a-3932c805afed"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-payments--uuid-">
            <blockquote>
            <p>Example response (200, Payment status):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;uuid&quot;: &quot;9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb80&quot;,
        &quot;status&quot;: &quot;confirmed&quot;,
        &quot;amount&quot;: 12500,
        &quot;currency&quot;: &quot;XOF&quot;,
        &quot;provider&quot;: &quot;mtn_momo&quot;,
        &quot;provider_reference&quot;: &quot;MM-98270341&quot;,
        &quot;confirmed_at&quot;: &quot;2026-08-20T08:05:00Z&quot;,
        &quot;reservation&quot;: {
            &quot;uuid&quot;: &quot;9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb70&quot;,
            &quot;status&quot;: &quot;paid&quot;,
            &quot;departure_uuid&quot;: &quot;9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e&quot;
        }
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-payments--uuid-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-payments--uuid-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-payments--uuid-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-payments--uuid-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-payments--uuid-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-payments--uuid-" data-method="GET"
      data-path="api/v1/payments/{uuid}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-payments--uuid-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-payments--uuid-"
                    onclick="tryItOut('GETapi-v1-payments--uuid-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-payments--uuid-"
                    onclick="cancelTryOut('GETapi-v1-payments--uuid-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-payments--uuid-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/payments/{uuid}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-payments--uuid-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-payments--uuid-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="uuid"                data-endpoint="GETapi-v1-payments--uuid-"
               value="6ff8f7f6-1eb3-3525-be4a-3932c805afed"
               data-component="url">
    <br>
<p>Example: <code>6ff8f7f6-1eb3-3525-be4a-3932c805afed</code></p>
            </div>
                    </form>

                    <h2 id="payments-POSTapi-v1-payments--payment_uuid--verify">Explicitly verify a pending payment with its provider.</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-payments--payment_uuid--verify">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://kora-ops-web.local/api/v1/payments/6ff8f7f6-1eb3-3525-be4a-3932c805afed/verify" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://kora-ops-web.local/api/v1/payments/6ff8f7f6-1eb3-3525-be4a-3932c805afed/verify"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-payments--payment_uuid--verify">
</span>
<span id="execution-results-POSTapi-v1-payments--payment_uuid--verify" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-payments--payment_uuid--verify"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-payments--payment_uuid--verify"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-payments--payment_uuid--verify" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-payments--payment_uuid--verify">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-payments--payment_uuid--verify" data-method="POST"
      data-path="api/v1/payments/{payment_uuid}/verify"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-payments--payment_uuid--verify', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-payments--payment_uuid--verify"
                    onclick="tryItOut('POSTapi-v1-payments--payment_uuid--verify');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-payments--payment_uuid--verify"
                    onclick="cancelTryOut('POSTapi-v1-payments--payment_uuid--verify');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-payments--payment_uuid--verify"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/payments/{payment_uuid}/verify</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-payments--payment_uuid--verify"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-payments--payment_uuid--verify"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>payment_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="payment_uuid"                data-endpoint="POSTapi-v1-payments--payment_uuid--verify"
               value="6ff8f7f6-1eb3-3525-be4a-3932c805afed"
               data-component="url">
    <br>
<p>Example: <code>6ff8f7f6-1eb3-3525-be4a-3932c805afed</code></p>
            </div>
                    </form>

                    <h2 id="payments-POSTapi-v1-payments--payment_uuid--cancel">POST api/v1/payments/{payment_uuid}/cancel</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-payments--payment_uuid--cancel">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://kora-ops-web.local/api/v1/payments/6ff8f7f6-1eb3-3525-be4a-3932c805afed/cancel" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://kora-ops-web.local/api/v1/payments/6ff8f7f6-1eb3-3525-be4a-3932c805afed/cancel"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-payments--payment_uuid--cancel">
</span>
<span id="execution-results-POSTapi-v1-payments--payment_uuid--cancel" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-payments--payment_uuid--cancel"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-payments--payment_uuid--cancel"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-payments--payment_uuid--cancel" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-payments--payment_uuid--cancel">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-payments--payment_uuid--cancel" data-method="POST"
      data-path="api/v1/payments/{payment_uuid}/cancel"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-payments--payment_uuid--cancel', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-payments--payment_uuid--cancel"
                    onclick="tryItOut('POSTapi-v1-payments--payment_uuid--cancel');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-payments--payment_uuid--cancel"
                    onclick="cancelTryOut('POSTapi-v1-payments--payment_uuid--cancel');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-payments--payment_uuid--cancel"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/payments/{payment_uuid}/cancel</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-payments--payment_uuid--cancel"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-payments--payment_uuid--cancel"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>payment_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="payment_uuid"                data-endpoint="POSTapi-v1-payments--payment_uuid--cancel"
               value="6ff8f7f6-1eb3-3525-be4a-3932c805afed"
               data-component="url">
    <br>
<p>Example: <code>6ff8f7f6-1eb3-3525-be4a-3932c805afed</code></p>
            </div>
                    </form>

                    <h2 id="payments-POSTapi-v1-payments--payment_uuid--confirm">Provider webhook / callback to confirm or fail a payment.</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-payments--payment_uuid--confirm">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/payments/6ff8f7f6-1eb3-3525-be4a-3932c805afed/confirm" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"status\": \"confirmed\",
    \"provider_reference\": \"b\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/payments/6ff8f7f6-1eb3-3525-be4a-3932c805afed/confirm"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "status": "confirmed",
    "provider_reference": "b"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-payments--payment_uuid--confirm">
</span>
<span id="execution-results-POSTapi-v1-payments--payment_uuid--confirm" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-payments--payment_uuid--confirm"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-payments--payment_uuid--confirm"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-payments--payment_uuid--confirm" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-payments--payment_uuid--confirm">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-payments--payment_uuid--confirm" data-method="POST"
      data-path="api/v1/payments/{payment_uuid}/confirm"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-payments--payment_uuid--confirm', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-payments--payment_uuid--confirm"
                    onclick="tryItOut('POSTapi-v1-payments--payment_uuid--confirm');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-payments--payment_uuid--confirm"
                    onclick="cancelTryOut('POSTapi-v1-payments--payment_uuid--confirm');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-payments--payment_uuid--confirm"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/payments/{payment_uuid}/confirm</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-payments--payment_uuid--confirm"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-payments--payment_uuid--confirm"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>payment_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="payment_uuid"                data-endpoint="POSTapi-v1-payments--payment_uuid--confirm"
               value="6ff8f7f6-1eb3-3525-be4a-3932c805afed"
               data-component="url">
    <br>
<p>Example: <code>6ff8f7f6-1eb3-3525-be4a-3932c805afed</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>status</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="status"                data-endpoint="POSTapi-v1-payments--payment_uuid--confirm"
               value="confirmed"
               data-component="body">
    <br>
<p>Example: <code>confirmed</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>success</code></li> <li><code>confirmed</code></li> <li><code>failed</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>provider_reference</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="provider_reference"                data-endpoint="POSTapi-v1-payments--payment_uuid--confirm"
               value="b"
               data-component="body">
    <br>
<p>Le champ value ne doit pas dépasser 255 caractères. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>provider_payload</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="provider_payload"                data-endpoint="POSTapi-v1-payments--payment_uuid--confirm"
               value=""
               data-component="body">
    <br>

        </div>
        </form>

                    <h2 id="payments-POSTapi-v1-payments-webhook-fedapay">FedaPay webhook endpoint.</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-payments-webhook-fedapay">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://kora-ops-web.local/api/v1/payments/webhook/fedapay" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://kora-ops-web.local/api/v1/payments/webhook/fedapay"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-payments-webhook-fedapay">
</span>
<span id="execution-results-POSTapi-v1-payments-webhook-fedapay" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-payments-webhook-fedapay"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-payments-webhook-fedapay"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-payments-webhook-fedapay" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-payments-webhook-fedapay">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-payments-webhook-fedapay" data-method="POST"
      data-path="api/v1/payments/webhook/fedapay"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-payments-webhook-fedapay', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-payments-webhook-fedapay"
                    onclick="tryItOut('POSTapi-v1-payments-webhook-fedapay');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-payments-webhook-fedapay"
                    onclick="cancelTryOut('POSTapi-v1-payments-webhook-fedapay');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-payments-webhook-fedapay"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/payments/webhook/fedapay</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-payments-webhook-fedapay"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-payments-webhook-fedapay"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="payments-POSTapi-v1-payments-webhook-mtn-momo">MTN MoMo webhook endpoint.</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-payments-webhook-mtn-momo">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://kora-ops-web.local/api/v1/payments/webhook/mtn-momo" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://kora-ops-web.local/api/v1/payments/webhook/mtn-momo"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-payments-webhook-mtn-momo">
</span>
<span id="execution-results-POSTapi-v1-payments-webhook-mtn-momo" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-payments-webhook-mtn-momo"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-payments-webhook-mtn-momo"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-payments-webhook-mtn-momo" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-payments-webhook-mtn-momo">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-payments-webhook-mtn-momo" data-method="POST"
      data-path="api/v1/payments/webhook/mtn-momo"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-payments-webhook-mtn-momo', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-payments-webhook-mtn-momo"
                    onclick="tryItOut('POSTapi-v1-payments-webhook-mtn-momo');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-payments-webhook-mtn-momo"
                    onclick="cancelTryOut('POSTapi-v1-payments-webhook-mtn-momo');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-payments-webhook-mtn-momo"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/payments/webhook/mtn-momo</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-payments-webhook-mtn-momo"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-payments-webhook-mtn-momo"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

            

        
    </div>
    <div class="dark-box">
                    <div class="lang-selector">
                                                        <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                                        <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                            </div>
            </div>
</div>
</body>
</html>
