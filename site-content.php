<?php
declare(strict_types=1);

require_once __DIR__ . '/content-functions.php';

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$site = content_load_site();
$projects = content_load_projects();
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
window.BODRBO_SITE_CONTENT = <?= json_encode($site, $flags) ?>;
window.BODRBO_PROJECTS = <?= json_encode($projects, $flags) ?>;

(() => {
  const site = window.BODRBO_SITE_CONTENT || {};
  const projects = window.BODRBO_PROJECTS || {};

  const setText = (selector, value) => {
    if (!value) return;
    document.querySelectorAll(selector).forEach(node => { node.textContent = value; });
  };

  const setLines = (selector, value) => {
    if (!value) return;
    document.querySelectorAll(selector).forEach(node => {
      const lines = String(value).split(/\r?\n/);
      node.replaceChildren();
      lines.forEach((line, index) => {
        if (index) node.append(document.createElement('br'));
        node.append(document.createTextNode(line));
      });
    });
  };

  const apply = () => {
    document.querySelectorAll('a[href^="tel:"]').forEach(link => {
      if (site.phone_href) link.href = `tel:${site.phone_href}`;
    });
    document.querySelectorAll('[data-contact="phone"]').forEach(link => {
      link.textContent = site.phone_display || link.textContent;
    });
    document.querySelectorAll('a[href^="mailto:"]').forEach(link => {
      link.textContent = site.email || link.textContent;
      if (site.email) link.href = `mailto:${site.email}`;
    });
    document.querySelectorAll('[data-contact="route"]').forEach(link => {
      if (site.route_url) link.href = site.route_url;
    });
    document.querySelectorAll('[data-contact="map"]').forEach(frame => {
      if (site.map_embed_url && frame.src !== site.map_embed_url) frame.src = site.map_embed_url;
    });

    setText('[data-contact="hours-header"]', site.hours_header);
    setText('[data-contact="hours-short"]', site.hours_short);
    setText('[data-contact="location-label"]', site.location_label);
    setLines('[data-contact="location-heading"]', site.location_heading);
    setLines('[data-contact="address"]', site.address);
    setLines('[data-contact="coordinates"]', site.latitude && site.longitude ? `${site.latitude} N\n${site.longitude} E` : '');

    document.querySelectorAll('[data-case-card]').forEach(card => {
      const project = projects[card.dataset.caseCard];
      if (!project) return;
      const title = card.querySelector('[data-case-title]');
      const summary = card.querySelector('[data-case-summary]');
      const category = card.querySelector('[data-case-category]');
      const image = card.querySelector('[data-case-image]');
      if (title) title.textContent = project.shortTitle || project.title;
      if (summary) summary.textContent = project.subtitle || project.summary;
      if (category) category.textContent = project.category || '';
      if (image && project.cover) {
        image.src = `/${String(project.cover).replace(/^\//, '')}`;
        image.alt = project.shortTitle || project.title || '';
      }
      if (project.slug && card.tagName === 'A') card.href = `/proekty/${project.slug}/`;
    });

    const schemaNode = document.querySelector('[data-business-schema]');
    if (schemaNode) {
      try {
        const schema = JSON.parse(schemaNode.textContent);
        schema.telephone = site.phone_href || schema.telephone;
        schema.email = site.email || schema.email;
        if (schema.address && site.address) schema.address.streetAddress = String(site.address).replace(/\n/g, ', ');
        if (schema.geo) {
          schema.geo.latitude = Number(site.latitude) || schema.geo.latitude;
          schema.geo.longitude = Number(site.longitude) || schema.geo.longitude;
        }
        if (Array.isArray(site.opening_days) && site.opening_days.length && site.opens && site.closes) {
          schema.openingHoursSpecification = [{
            '@type': 'OpeningHoursSpecification',
            dayOfWeek: site.opening_days,
            opens: site.opens,
            closes: site.closes,
          }];
        }
        const sameAs = [site.telegram_url, site.whatsapp_url].filter(Boolean);
        if (sameAs.length) schema.sameAs = sameAs;
        schemaNode.textContent = JSON.stringify(schema);
      } catch (_) {}
    }
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', apply, { once: true });
  else apply();
})();
