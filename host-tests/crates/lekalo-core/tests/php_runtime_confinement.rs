#![cfg(unix)]

use lekalo_core::target_protocol::{runtime_exchange, transport};
use std::path::Path;

#[test]
fn unix_confined_php_runtime_qualification() {
    let root = tempfile::tempdir().unwrap();
    let source = std::fs::read_to_string(
        Path::new(env!("CARGO_MANIFEST_DIR"))
            .join("../../tests/external/lekalo-php/src/ai-lint.php"),
    )
    .expect("install the pinned PHP package with scripts/external-tool.mjs --provision-php");
    let script = root.path().join("php-runtime.php");
    std::fs::write(
        &script,
        format!(
            "<?php\nclass RequestRefusal extends RuntimeException {{}}\n{}\nlint_require_tokenizer(); if (isset($argv[1]) && @file_get_contents($argv[1]) !== false) {{ fwrite(STDERR, 'private-runtime-file-readable'); exit(24); }} echo 'lekalo-confined-php-ok';",
            source.strip_prefix("<?php").expect("PHP source header")
        ),
    )
    .unwrap();
    #[cfg(target_os = "macos")]
    let protected_runtime = ["/opt/homebrew/Cellar", "/usr/local/Cellar"]
        .iter()
        .find(|path| Path::new(path).is_dir())
        .map(|base| {
            let owned = tempfile::Builder::new()
                .prefix("lekalo-runtime-boundary-")
                .tempdir_in(base)
                .expect("owned Homebrew boundary fixture");
            let lib = owned.path().join("1/lib");
            std::fs::create_dir_all(&lib).unwrap();
            let private = lib.join("private.ini");
            std::fs::write(&private, b"not-a-runtime-library").unwrap();
            (owned, private)
        });
    let args = vec![script.to_string_lossy().into_owned()];
    #[cfg(target_os = "macos")]
    let args = {
        let mut args = args;
        if let Some((_, private)) = &protected_runtime {
            args.push(private.to_string_lossy().into_owned());
        }
        args
    };
    let command = transport::AdapterCommand {
        program: "php".into(),
        args,
    };
    let limits = transport::TransportLimits {
        timeout_ms: 10_000,
        max_output_bytes: 1_024,
        max_stderr_bytes: 8_192,
        ..Default::default()
    };
    let result = runtime_exchange(&command, b"", root.path(), &limits, None).unwrap();
    assert_eq!(
        result.exit_code,
        0,
        "private synthetic PHP launch evidence: stderr={:?}",
        String::from_utf8_lossy(&result.stderr)
    );
    assert_eq!(result.stdout, b"lekalo-confined-php-ok");
}
