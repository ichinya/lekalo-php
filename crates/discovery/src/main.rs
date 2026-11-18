use serde_json::{json, Value};
use std::io::{self, Read};
fn main() -> Result<(), Box<dyn std::error::Error>> {
    let mut bytes = Vec::new();
    io::stdin()
        .take(8 * 1024 * 1024 + 1)
        .read_to_end(&mut bytes)?;
    if bytes.len() > 8 * 1024 * 1024 {
        return Err("request-limit".into());
    }
    let request: Value = serde_json::from_slice(&bytes)?;
    if request["protocol"] != "lekalo/runtime/v1"
        || !request["request_id"]
            .as_str()
            .is_some_and(|id| id.len() == 71 && id.starts_with("sha256:"))
    {
        return Err("request-envelope".into());
    }
    let result = dispatch(
        request["operation"].as_str().ok_or("operation")?,
        &request["input"],
    )?;
    print!(
        "{}",
        json!({"protocol":"lekalo/runtime/v1","request_id":request["request_id"],"result":result})
    );
    Ok(())
}

const ID: &str = "lekalo-php-discovery";
const FILES: &[&str] = &["composer.json", "composer.lock", "artisan"];
const READS: &[&str] = &["composer.json"];

fn path_dir(path: &str) -> &str {
    path.rsplit_once('/').map(|(d, _)| d).unwrap_or(".")
}
fn dispatch(op: &str, input: &Value) -> Result<Value, Box<dyn std::error::Error>> {
    if op == "describe" {
        return Ok(
            json!({"id":ID,"version":"0.1.0","rules_api":[],"capabilities":["discovery/v1"],"operations":["discovery.describe","discovery.inspect"]}),
        );
    }
    if op == "discovery.describe" {
        return Ok(json!({"schema":"lekalo/discovery/v1","files":FILES,"reads":READS}));
    }
    if op != "discovery.inspect" || input["schema"] != "lekalo/discovery/v1" {
        return Err("operation-input".into());
    }
    let files = input["files"].as_array().ok_or("files")?;
    if files.len() > 10000 {
        return Err("files-limit".into());
    }
    let mut out = json!({"schema":"lekalo/discovery/v1","manifests":[],"managers":[],"hints":[],"workspaces":[],"tools":[],"gates":[],"modules":[],"names":[],"refused":[]});

    use std::collections::BTreeSet;
    let artisans: BTreeSet<_> = files
        .iter()
        .filter_map(|f| f["path"].as_str())
        .filter(|p| p.rsplit('/').next() == Some("artisan"))
        .collect();
    for f in files {
        let path = f["path"].as_str().ok_or("path")?;
        let name = path.rsplit('/').next();
        if name == Some("composer.lock") {
            push(
                &mut out,
                "managers",
                json!({"path":path,"detail":"composer"}),
            );
        }
        if name != Some("composer.json") {
            continue;
        }
        push(&mut out, "manifests", json!({"path":path,"detail":"php"}));
        // Composer manifests are JSON. Reject YAML and duplicate-key documents
        // refused by the host parser instead of trusting partial metadata.
        let parsed = f["content"]
            .as_str()
            .and_then(|text| serde_json::from_str::<Value>(text).ok());
        if parsed.is_none() || parsed.as_ref() != Some(&f["value"]) {
            push(&mut out, "refused", json!(path));
        }
        let value = parsed
            .as_ref()
            .filter(|v| **v == f["value"])
            .unwrap_or(&Value::Null);
        let package = value["name"].as_str();
        push(
            &mut out,
            "modules",
            json!({"path":path_dir(path),"manifest":path,"name":package,"priority":20,"standalone":false,"observe_when_scoped":true}),
        );
        if let Some(package) = package {
            push(
                &mut out,
                "names",
                json!({"path":path,"name":package,"priority":20,"source":"composer.json#name"}),
            );
        }
        if !value.is_object() {
            continue;
        }
        for field in ["require", "require-dev"] {
            for (key, label) in [
                ("laravel/framework", "laravel"),
                ("symfony/framework-bundle", "symfony"),
            ] {
                if value[field].get(key).is_some() {
                    push(
                        &mut out,
                        "hints",
                        json!({"path":path,"kind":"framework","label":label,"field":field,"confidence":"high"}),
                    );
                }
            }
        }
        if let Some(scripts) = value["scripts"].as_object() {
            for script in scripts.keys() {
                let gate = match script.as_str() {
                    "build" | "compile" => "build",
                    "check" | "ci" => "check",
                    "lint" => "lint",
                    "test" => "test",
                    _ => continue,
                };
                push(
                    &mut out,
                    "gates",
                    json!({"gate":gate,"command":format!("composer {script}"),"source":"workflow","evidence":format!("{path}#scripts.{script}"),"confidence":"high"}),
                );
            }
        }
        let artisan = format!("{}artisan", path.strip_suffix("composer.json").unwrap());
        if artisans.contains(artisan.as_str()) {
            push(
                &mut out,
                "gates",
                json!({"gate":"test","command":"php artisan test","source":"derived","evidence":artisan,"confidence":"medium"}),
            );
        }
    }
    Ok(out)
}
fn push(out: &mut Value, field: &str, row: Value) {
    out[field].as_array_mut().unwrap().push(row);
}
