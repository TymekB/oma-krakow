from __future__ import annotations

import os

import hc.settings as _upstream

STYLESHEET = '<link rel="stylesheet" href="/static/css/oma.css">'

SECURE_PROXY_SSL_HEADER = ("HTTP_X_FORWARDED_PROTO", "https")

_SITE_ROOT = os.environ.get("SITE_ROOT", "")
CSRF_TRUSTED_ORIGINS = [_SITE_ROOT] if _SITE_ROOT.startswith("https://") else []


class OmaBrandingMiddleware:
    def __init__(self, get_response) -> None:
        self.get_response = get_response

    def __call__(self, request):
        response = self.get_response(request)

        if getattr(response, "streaming", False):
            return response

        if "text/html" not in response.get("Content-Type", ""):
            return response

        html = response.content.decode(response.charset)

        if "</head>" not in html or "oma.css" in html:
            return response

        response.content = html.replace("</head>", STYLESHEET + "</head>", 1).encode(response.charset)

        if response.has_header("Content-Length"):
            response["Content-Length"] = str(len(response.content))

        return response


MIDDLEWARE = [*_upstream.MIDDLEWARE, f"{__name__}.OmaBrandingMiddleware"]
