## Sitemap Generator

A simple cli / docker application to generate
sitemaps. Useful to generate sitemap for static small websites
to larger websites.

### To build the cli application

```
php sitemap app:build --build-version=0.0.1
```

### Docker usage
```
#pull the image
docker pull ghcr.io/flyvolcano/sitemap-generator

#run the image. The sitemap will be generated to the current folder.
docker run --rm -v $(pwd):/app sitemap generate https://example.com
```

### Blacklisting URLs

Create a `blacklist.map` file in the directory where the cli 
file exists or map the docker volume. Add one URL per line 
and these URLs will be skipped from the sitemaps.
