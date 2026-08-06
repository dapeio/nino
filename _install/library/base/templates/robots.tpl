User-agent: *
Allow: /
Disallow: /_editor/
Disallow: /_admin/
Disallow: /.cache/
Disallow: /data/

# AI assistants/crawlers - allowed by default so this site can be found and
# cited via AI search/chat products. Flip any "Allow: /" below to
# "Disallow: /" to opt that bot out (eg. if the project doesn't want its
# content used for model training)
User-agent: GPTBot
Allow: /
User-agent: ClaudeBot
Allow: /
User-agent: PerplexityBot
Allow: /
User-agent: Google-Extended
Allow: /
User-agent: CCBot
Allow: /

Sitemap: https://[[/website/url]]/sitemap.xml
