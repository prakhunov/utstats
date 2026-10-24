{
  description = "UTStats (utstats.passionategaming.eu) dev shell";

  inputs.nixpkgs.url = "github:NixOS/nixpkgs/nixos-unstable";

  outputs =
    { nixpkgs, ... }:
    let
      systems = [ "x86_64-linux" "aarch64-linux" ];
      forAll = f: nixpkgs.lib.genAttrs systems (system: f nixpkgs.legacyPackages.${system});
    in
    {
      devShells = forAll (pkgs:
        let
          # Match the container's extension set (see docker/php/Containerfile).
          php = pkgs.php85.buildEnv {
            extensions = { enabled, all }: enabled ++ (with all; [ pdo_mysql gd ftp bz2 ]);
            extraConfig = "memory_limit = 1G";
          };
        in
        {
          default = pkgs.mkShell {
            packages = [
              php
              php.packages.composer
              pkgs.just
              pkgs.mariadb.client
              pkgs.caddy
              pkgs.python3 # scripts/crawl_diff.py
              pkgs.ssm-session-manager-plugin # just host-shell
              pkgs.opentofu # infra/ and the just recipes that read its outputs
              pkgs.awscli2
              pkgs.jq
              # `podman compose` provider = docker-compose v2 (same as FragMath's devshell);
              # it drives the rootless podman socket exported as DOCKER_HOST below.
              pkgs.docker-compose
            ];
            # Lint/analysis tools (phpstan, phpcs + PHPCompatibility, rector) come from composer require-dev.
            shellHook = ''
              export PATH="$PWD/vendor/bin:$PATH"
            '';
          };
        });
    };
}
