<?php
/*
Plugin Name: WP Encrypt CLI
Plugin URI: http://ircf.fr/
Description: A simple CLI SSL certificate generator for WordPress network.
Version: 0.1
Author: IRCF
Author URI: http://ircf.fr/
License: GPL2
*/

// Check configuration
if (exec('which certbot-auto') == '') throw new Exception('certbot is required');
if (exec('which wp') == '') throw new Exception('wp cli is required');

// Let's encrypt cleaner helper
// Warning : this removes ALL let's encrypt config from server
// TODO fix --expand and remove this
function wp_encrypt_cli_clean(){
  exec('cd /etc/letsencrypt && rm -rf live/* renewal/* archive/* csr/* keys/*');
}

// Domains helper
function wp_encrypt_cli_domains($opts = array()){
  if (!isset($opts['mapping'])) $opts['mapping'] = false;
  if (!isset($opts['subdomains'])) $opts['subdomains'] = false;
  global $wpdb;
  $domains = $wpdb->get_col($sql = "
    SELECT DISTINCT tmp.domain FROM (
      SELECT domain FROM $wpdb->blogs WHERE spam=0 AND deleted=0 AND archived=0
      ".($opts['mapping'] ? "UNION
      SELECT domain FROM wp_domain_mapping WHERE EXISTS(
        SELECT 1 FROM $wpdb->blogs WHERE $wpdb->blogs.spam=0 AND $wpdb->blogs.deleted=0 AND $wpdb->blogs.archived=0 AND $wpdb->blogs.blog_id=wp_domain_mapping.blog_id
      )" : "")."
    ) AS tmp
    WHERE domain<>'".DOMAIN_CURRENT_SITE."' ".($opts['subdomains'] ? "" : "AND domain NOT LIKE '%.".DOMAIN_CURRENT_SITE."'")."
    ORDER BY tmp.domain
  ");
  return $domains;
}

// Certbot helper
function wp_encrypt_cli_certbot($domains){
  $network_domain = DOMAIN_CURRENT_SITE;
  $email = get_option('admin_email');
  $document_root = ABSPATH;
  if (is_array($domains)) $domains = implode(' -d ', $domains);
  if (!empty($domains)) $domains = '-d ' . $domains;
  $cmd = "certbot-auto certonly --non-interactive --allow-subset-of-names --expand --force-renewal --webroot -m $email -w $document_root -d $network_domain $domains";
  return exec($cmd);
}

// CLI command
function wp_encrypt_cli_command(){
  $opts = array(); // TODO get $opts from $argv
  $domains = wp_encrypt_cli_domains($opts);
  wp_encrypt_cli_clean();
  $result = wp_encrypt_cli_certbot($domains);
  if ($result){
    WP_CLI::success( $result );
  }
}
if (class_exists('WP_CLI')){
  WP_CLI::add_command('wp-encrypt-cli', 'wp_encrypt_cli_command');
}
