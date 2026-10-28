(() => {
    "use strict";
    const c = window.badgeStudio,
        $ = (id) => document.getElementById(id),
        form = $("design-form"),
        canvas = document.querySelector(".badge");
    const fields = structuredClone(c.fields);
    for (const f of Object.values(fields)) {
        for (const k of ["x", "y", "w", "h", "size"]) f[k] = Number(f[k]);
        f.visible = Boolean(Number(f.visible));
        f.bold = Boolean(Number(f.bold));
        f.color = f.color || null;
        f.case = f.case || "none";
        f.border = f.border || "none";
        f.border_color = f.border_color || null;
        f.border_width = Number(f.border_width || 0.5);
        f.fill = f.fill || null;
    }
    fields.name.emphasis = fields.name.emphasis || "none";
    fields.name.small_size = Number(fields.name.small_size || 12);
    const GRAPHIC = ["qr", "company_logo", "event_logo"];
    const TRANSFORM = { upper: "uppercase", lower: "lowercase", title: "capitalize" };
    /** Mirrors BadgeDesign::textStyle() so the preview matches the PDF. */
    function textStyle(key, f) {
        if (GRAPHIC.includes(key)) return {};
        const boxed = f.border !== "none" || f.fill;
        return {
            textTransform: TRANSFORM[f.case] || "none",
            border:
                f.border === "none"
                    ? ""
                    : `${f.border_width}mm solid ${f.border_color || "currentColor"}`,
            borderRadius: f.border === "rounded" ? "2mm" : "",
            padding: boxed ? "0.8mm 1.5mm" : "",
            ...(key === "category" ? {} : { background: f.fill || "" }),
        };
    }
    let selected = "name",
        dirty = c.hasErrors,
        artwork = c.artwork,
        objectUrl = null,
        imageSize = null;
    const dimensions = () =>
        $("badge-size").value === "A5" ? [148, 210] : [105, 148];
    const attendee = () => c.attendees[$("preview-attendee").value];
    const fieldElement = (key) => canvas.querySelector(`[data-field="${key}"]`);
    function formatName(name) {
        const p = name.trim().split(/\s+/u);
        return $("name-format").value === "initials" && p.length > 2
            ? [
                  p[0],
                  ...p
                      .slice(1, -1)
                      .map((x) => [...x][0].toLocaleUpperCase() + "."),
                  p.at(-1),
              ].join(" ")
            : name;
    }
    /** Mirrors BadgeDesign::nameParts(): [big, rest], or null for one line. */
    function nameParts(name) {
        const p = name.trim().split(/\s+/u).filter(Boolean),
            emphasis = fields.name.emphasis;
        if (p.length < 2 || !["first", "last"].includes(emphasis)) return null;
        const big = emphasis === "first" ? p.shift() : p.pop();
        return [big, p.join(" ")];
    }
    function renderName(el, name) {
        const parts = nameParts(name);
        if (!parts) {
            el.textContent = name;
            return;
        }
        const [big, rest] = [document.createElement("span"), document.createElement("span")];
        big.className = "name-big";
        big.textContent = parts[0];
        Object.assign(big.style, { display: "block", lineHeight: "1.1" });
        rest.className = "name-rest";
        rest.textContent = parts[1];
        Object.assign(rest.style, { display: "block", lineHeight: "1.2", fontSize: fields.name.small_size + "pt" });
        el.replaceChildren(big, rest);
    }
    /** Whether a person (their data-* attributes) matches the gender, category, country (and printed or not) chosen in the selects named prefix-gender etc. */
    function inGroup(el, prefix) {
        return ["gender", "category", "country", "printed"].every(
            (key) => !$(`${prefix}-${key}`)?.value || el.dataset[key] === $(`${prefix}-${key}`).value,
        );
    }
    /** Every word typed must appear in the name, in any order; stray spaces are ignored. */
    function matchesWords(name, query) {
        const lower = name.toLocaleLowerCase();
        return query.toLocaleLowerCase().split(/\s+/).filter(Boolean).every((word) => lower.includes(word));
    }
    const inPrintGroup = (row) => inGroup(row, "print");
    function ids() {
        return [...document.querySelectorAll("#attendee-list input:checked")]
            .filter((x) => inPrintGroup(x.parentElement))
            .map((x) => x.value);
    }
    function updatePrint() {
        const count = ids().length;
        $("print-count").textContent = `${count} attendees selected`;
        for (const id of ["sample-pdf", "download-pdf"])
            $(id).disabled = dirty || !count;
        for (const id of ["mark-printed", "unmark-printed"])
            $(id).disabled = !count;
        $("paper-help").textContent =
            $("badge-size").value === "A5"
                ? "A4: one A5 badge centred on each sheet."
                : "A4: two A6 badges per landscape sheet, with space for cutting.";
    }
    function markDirty() {
        dirty = true;
        $("review-result").textContent = "";
        $("save-status").textContent =
            "Unsaved changes — save before downloading.";
        updatePrint();
    }
    function sync() {
        const root = $("field-inputs");
        root.replaceChildren();
        for (const [key, f] of Object.entries(fields))
            for (const [prop, val] of Object.entries(f)) {
                if (["color", "border_color", "fill"].includes(prop) && !val) continue; // no override: let the server fall back to the default
                const el = document.createElement("input");
                el.type = "hidden";
                el.name = `badge_fields[${key}][${prop}]`;
                el.value =
                    prop === "visible" || prop === "bold"
                        ? val
                            ? "1"
                            : "0"
                        : String(val);
                root.append(el);
            }
    }
    function inspect() {
        const f = fields[selected];
        $("field-picker").value = selected;
        for (const key of ["x", "y", "w", "h", "size"])
            $("field-" + key).value = f[key];
        $("field-align").value = f.align;
        $("field-visible").checked = f.visible;
        $("field-visible").disabled = selected === "qr";
        const isGraphic = ["qr", "company_logo", "event_logo"].includes(
            selected,
        );
        $("field-bold").checked = f.bold;
        $("field-bold").disabled = isGraphic;
        $("field-custom-color").checked = f.color !== null;
        $("field-custom-color").disabled = isGraphic;
        $("field-color").value = f.color || $("accent").value;
        $("field-color").disabled = isGraphic || f.color === null;
        for (const id of ["field-size", "field-align"])
            $(id).disabled = isGraphic;
        $("field-text-style").classList.toggle("hidden", isGraphic);
        $("field-case").value = f.case;
        $("field-border").value = f.border;
        $("field-border-options").classList.toggle("hidden", f.border === "none");
        $("field-border-color").value = f.border_color || f.color || $("accent").value;
        $("field-border-width").value = String(f.border_width);
        $("field-fill-on").checked = f.fill !== null;
        $("field-fill").value = f.fill || "#FFFFFF";
        $("field-fill").disabled = f.fill === null;
        $("field-name-wrap").classList.toggle("hidden", selected !== "name");
        $("field-emphasis").value = fields.name.emphasis;
        $("field-small-size").value = fields.name.small_size;
        $("field-small-wrap").classList.toggle("hidden", fields.name.emphasis === "none");
        $("field-text-wrap").classList.toggle("hidden", selected !== "custom");
        if (selected === "custom") $("field-text").value = f.text || "";
        canvas
            .querySelectorAll(".badge-field")
            .forEach((el) =>
                el.classList.toggle("selected", el.dataset.field === selected),
            );
    }
    function syncVisibilityList() {
        for (const el of document.querySelectorAll(".field-visibility-toggle"))
            el.checked = fields[el.dataset.field].visible;
    }
    function clamp(f, key) {
        const [w, h] = dimensions();
        f.w = Math.max(
            key === "qr" ? Math.ceil(250000 / w) / 100 : 5,
            Math.min(100, f.w),
        );
        f.h = Math.max(
            key === "qr" ? Math.ceil(250000 / h) / 100 : 3,
            Math.min(100, f.h),
        );
        f.x = Math.max(0, Math.min(95, 100 - f.w, f.x));
        f.y = Math.max(0, Math.min(95, 100 - f.h, f.y));
        f.size = Math.max(6, Math.min(48, f.size));
        if (key === "name") f.small_size = Math.max(6, Math.min(48, f.small_size));
        for (const prop of ["x", "y", "w", "h"])
            f[prop] = Math.round(f[prop] * 100) / 100;
        if (key === "qr") f.visible = true;
    }
    function renderValues(data) {
        if (!data) return;
        for (const [key, val] of Object.entries(data.values)) {
            const el = fieldElement(key)?.querySelector(".field-content");
            if (!el) continue;
            if (key === "name") renderName(el, formatName(data.fullName));
            else el.textContent = val;
        }
        const color = [
            ...document.querySelectorAll("input[data-category]"),
        ].find((x) => x.dataset.category === data.values.category);
        fieldElement("category").style.background =
            fields.category.fill ||
            ($("category-treatment").value === "category" && color
                ? color.value
                : $("primary").value);
    }
    function warnings() {
        const results = [],
            active = [];
        for (const [key, f] of Object.entries(fields)) {
            if (!f.visible) continue;
            const el = fieldElement(key),
                content = el.querySelector(".field-content");
            if (
                content?.textContent.trim() &&
                (el.scrollHeight > el.clientHeight + 1 ||
                    el.scrollWidth > el.clientWidth + 1)
            )
                results.push(
                    `${c.labels[key]} does not fit. Enlarge its box or reduce the font size.`,
                );
            if (
                key === "qr" ||
                content?.textContent.trim() ||
                el.querySelector("img")
            )
                active.push([key, f]);
        }
        for (let i = 0; i < active.length; i++)
            for (let j = i + 1; j < active.length; j++) {
                const [a, x] = active[i],
                    [b, y] = active[j];
                if (
                    x.x < y.x + y.w &&
                    x.x + x.w > y.x &&
                    x.y < y.y + y.h &&
                    x.y + x.h > y.y
                )
                    results.push(
                        `${c.labels[a]} overlaps ${c.labels[b].toLowerCase()}.`,
                    );
            }
        return results;
    }
    function render() {
        const [w, h] = dimensions(),
            layout = $("layout").value;
        Object.assign(canvas.style, {
            width: w + "mm",
            height: h + "mm",
            fontFamily: $("font").value,
            color: $("accent").value,
        });
        canvas.className = "badge layout-" + layout;
        canvas.querySelector(".badge-stripe").style.background =
            $("primary").value;
        const art = canvas.querySelector(".badge-art"),
            show = ["background", "image_header", "split"].includes(layout),
            removed = $("remove-artwork")?.checked && !objectUrl;
        art.style.display = show && !removed ? "" : "none";
        const sectioned = ["image_header", "split"].includes(layout),
            aw = w * (sectioned ? 0.86 : 1),
            ah = h * (sectioned ? 0.17 : 1);
        Object.assign(art.style, {
            left: (sectioned ? w * 0.07 : 0) + "mm",
            top: (sectioned ? h * 0.04 : 0) + "mm",
            width: aw + "mm",
            height: ah + "mm",
        });
        let artImg = art.querySelector("img");
        if (artwork && !artImg) {
            artImg = document.createElement("img");
            artImg.alt = "Company badge artwork";
            art.append(artImg);
        }
        if (artImg) {
            if (artwork && artImg.getAttribute("src") !== artwork)
                artImg.src = artwork;
            artImg.style.display = artwork ? "" : "none";
            let iw = aw,
                ih = ah;
            if (sectioned && imageSize) {
                const scale = Math.max(aw / imageSize[0], ah / imageSize[1]);
                iw = imageSize[0] * scale;
                ih = imageSize[1] * scale;
            }
            Object.assign(artImg.style, {
                width: iw + "mm",
                height: ih + "mm",
                left:
                    ((aw - iw) * form.elements.badge_image_position_x.value) /
                        100 +
                    "mm",
                top:
                    ((ah - ih) * form.elements.badge_image_position_y.value) /
                        100 +
                    "mm",
            });
        }
        $("crop-controls").classList.toggle(
            "hidden",
            !["image_header", "split"].includes(layout),
        );
        $("artwork-panel").classList.toggle("hidden", !show);
        $("category-colors").classList.toggle(
            "hidden",
            $("category-treatment").value !== "category",
        );
        $("resolution-help").textContent =
            w === 105
                ? "Suggested artwork: 1240 × 1748 px or larger (300 dpi)."
                : "Suggested artwork: 1748 × 2480 px or larger (300 dpi).";
        const issues = [];
        if (show && (!artwork || removed))
            issues.push("Upload artwork before saving this design.");
        if (imageSize && layout === "background") {
            if (Math.abs(imageSize[0] / imageSize[1] - w / h) > 0.025)
                issues.push(
                    "Artwork proportions differ from the badge. It will stretch to fill; export at the dimensions above.",
                );
            if (
                imageSize[0] < (w / 25.4) * 200 ||
                imageSize[1] < (h / 25.4) * 200
            )
                issues.push(
                    "Artwork may look soft in print. Use a higher-resolution export.",
                );
        }
        $("art-warning").textContent = issues.join(" ");
        $("dimensions").textContent = `${w} × ${h} mm`;
        for (const [key, f] of Object.entries(fields)) {
            clamp(f, key);
            const el = fieldElement(key);
            Object.assign(el.style, {
                left: (f.x * w) / 100 + "mm",
                top: (f.y * h) / 100 + "mm",
                width: (f.w * w) / 100 + "mm",
                height: (f.h * h) / 100 + "mm",
                fontSize: f.size + "pt",
                textAlign: f.align,
                fontWeight: f.bold ? "bold" : "normal",
                color: f.color || "",
                display: f.visible ? "" : "none",
                ...textStyle(key, f),
            });
            if (key === "qr") {
                const img = el.querySelector("img");
                if (img)
                    img.style.width = img.style.height =
                        Math.min((f.w * w) / 100, (f.h * h) / 100) + "mm";
            }
        }
        renderValues(attendee());
        const customEl = fieldElement("custom")?.querySelector(".field-content");
        if (customEl) customEl.textContent = fields.custom.text || "";
        sync();
        inspect();
        updatePrint();
        $("canvas-wrap").classList.toggle("guides", $("guides").checked);
        syncVisibilityList();
        requestAnimationFrame(
            () => ($("layout-warnings").textContent = warnings().join(" ")),
        );
    }
    for (const el of form.querySelectorAll("[name]")) {
        if (el.type === "hidden" || el.type === "file") continue;
        el.addEventListener("input", () => {
            markDirty();
            render();
        });
    }
    for (const el of document.querySelectorAll("[data-property]"))
        el.addEventListener("change", () => {
            if (el.value === "" || !Number.isFinite(Number(el.value))) return;
            fields[selected][el.dataset.property] = Number(el.value);
            markDirty();
            render();
        });
    $("field-visible").addEventListener("change", () => {
        fields[selected].visible = $("field-visible").checked;
        markDirty();
        render();
    });
    $("field-bold").addEventListener("change", () => {
        fields[selected].bold = $("field-bold").checked;
        markDirty();
        render();
    });
    $("field-custom-color").addEventListener("change", () => {
        fields[selected].color = $("field-custom-color").checked
            ? $("field-color").value
            : null;
        markDirty();
        render();
    });
    $("field-color").addEventListener("input", () => {
        if (!$("field-custom-color").checked) return;
        fields[selected].color = $("field-color").value;
        markDirty();
        render();
    });
    for (const el of document.querySelectorAll(".field-visibility-toggle"))
        el.addEventListener("change", () => {
            fields[el.dataset.field].visible = el.checked;
            markDirty();
            render();
        });
    const restyle = (change) => {
        change(fields[selected]);
        markDirty();
        render();
    };
    $("field-case").addEventListener("change", () => restyle((f) => (f.case = $("field-case").value)));
    $("field-border").addEventListener("change", () =>
        restyle((f) => {
            f.border = $("field-border").value;
            if (f.border !== "none" && !f.border_color) f.border_color = $("field-border-color").value;
        }),
    );
    $("field-border-color").addEventListener("input", () => restyle((f) => (f.border_color = $("field-border-color").value)));
    $("field-border-width").addEventListener("change", () => restyle((f) => (f.border_width = Number($("field-border-width").value))));
    $("field-fill-on").addEventListener("change", () => restyle((f) => (f.fill = $("field-fill-on").checked ? $("field-fill").value : null)));
    $("field-fill").addEventListener("input", () => {
        if ($("field-fill-on").checked) restyle((f) => (f.fill = $("field-fill").value));
    });
    for (const [id, value] of [["all-upper", "upper"], ["all-case-reset", "none"]])
        $(id).addEventListener("click", () => {
            for (const [key, f] of Object.entries(fields)) if (!GRAPHIC.includes(key)) f.case = value;
            markDirty();
            render();
        });
    $("field-emphasis").addEventListener("change", () => restyle(() => (fields.name.emphasis = $("field-emphasis").value)));
    $("field-small-size").addEventListener("change", () => {
        const size = Number($("field-small-size").value);
        if ($("field-small-size").value !== "" && Number.isFinite(size)) restyle(() => (fields.name.small_size = size));
    });
    $("field-align").addEventListener("change", () => {
        fields[selected].align = $("field-align").value;
        markDirty();
        render();
    });
    $("field-text").addEventListener("input", () => {
        fields.custom.text = $("field-text").value;
        markDirty();
        render();
    });
    $("field-picker").addEventListener("change", () => {
        selected = $("field-picker").value;
        inspect();
    });
    $("reset-fields").addEventListener("click", () => {
        Object.assign(
            fields,
            structuredClone(
                ["image_header", "split"].includes($("layout").value)
                    ? c.headerDefaults
                    : c.defaults,
            ),
        );
        markDirty();
        render();
    });
    $("guides").addEventListener("change", render);
    $("preview-attendee").addEventListener("change", () => {
        const data = attendee(),
            img = fieldElement("qr").querySelector("img");
        if (data && img) {
            img.onload = () => (img.style.visibility = "");
            img.onerror = () => {
                $("layout-warnings").textContent =
                    "Could not load the QR code. Reload the page before printing.";
            };
            img.style.visibility = "hidden";
            img.src = data.qr;
        }
        render();
    });
    // Narrows the preview list to matching people. Options are removed rather than hidden,
    // which Safari ignores; the current person stays chosen while they still match.
    const previewOptions = [...$("preview-attendee").options];
    function filterPreview() {
        const select = $("preview-attendee"),
            current = select.value,
            matches = previewOptions.filter(
                (o) => matchesWords(o.textContent, $("preview-search").value) && inGroup(o, "preview"),
            ),
            none = new Option("No one matches", "", false, false);
        none.disabled = true;
        select.replaceChildren(...(matches.length ? matches : [none]));
        $("preview-count").textContent = `${matches.length} of ${previewOptions.length} shown`;
        if (!matches.length) return;
        select.value = matches.some((o) => o.value === current) ? current : matches[0].value;
        if (select.value !== current) select.dispatchEvent(new Event("change"));
    }
    if ($("preview-search")) {
        $("preview-search").addEventListener("input", filterPreview);
        for (const key of ["gender", "category", "country"])
            $(`preview-${key}`).addEventListener("change", filterPreview);
        filterPreview();
    }
    $("artwork").addEventListener("change", () => {
        const file = $("artwork").files[0];
        if (!file) {
            artwork = c.artwork;
            imageSize = null;
            objectUrl = null;
            markDirty();
            render();
            return;
        }
        if (
            file.size > 4194304 ||
            !["image/png", "image/jpeg", "image/webp"].includes(file.type)
        ) {
            $("art-warning").textContent =
                "Choose a PNG, JPG or WebP no larger than 4 MB.";
            $("artwork").value = "";
            return;
        }
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(file);
        artwork = objectUrl;
        const img = new Image();
        img.onload = () => {
            imageSize = [img.naturalWidth, img.naturalHeight];
            render();
        };
        img.src = artwork;
        markDirty();
        render();
    });
    canvas.addEventListener("pointerdown", (event) => {
        const el = event.target.closest("[data-field]");
        if (!el) return;
        event.preventDefault();
        selected = el.dataset.field;
        inspect();
        el.focus();
        const rect = canvas.getBoundingClientRect(),
            start = {
                x: event.clientX,
                y: event.clientY,
                fx: fields[selected].x,
                fy: fields[selected].y,
            };
        el.setPointerCapture(event.pointerId);
        const move = (e) => {
            fields[selected].x =
                start.fx + ((e.clientX - start.x) / rect.width) * 100;
            fields[selected].y =
                start.fy + ((e.clientY - start.y) / rect.height) * 100;
            markDirty();
            render();
        };
        const end = () => {
            el.removeEventListener("pointermove", move);
            el.removeEventListener("pointerup", end);
            el.removeEventListener("pointercancel", end);
        };
        el.addEventListener("pointermove", move);
        el.addEventListener("pointerup", end);
        el.addEventListener("pointercancel", end);
    });
    canvas.addEventListener("keydown", (event) => {
        const el = event.target.closest("[data-field]"),
            moves = {
                ArrowLeft: ["x", -1],
                ArrowRight: ["x", 1],
                ArrowUp: ["y", -1],
                ArrowDown: ["y", 1],
            };
        if (!el || !moves[event.key]) return;
        selected = el.dataset.field;
        event.preventDefault();
        const [key, delta] = moves[event.key];
        fields[selected][key] += delta * (event.shiftKey ? 2 : 0.25);
        markDirty();
        render();
    });
    $("review-all").addEventListener("click", () => {
        const failures = [];
        for (const data of Object.values(c.attendees)) {
            renderValues(data);
            const issues = warnings();
            if (issues.length)
                failures.push(`${data.fullName}: ${issues.join(" ")}`);
        }
        renderValues(attendee());
        $("review-result").textContent = !Object.keys(c.attendees).length
            ? "Add confirmed attendees to check real names."
            : failures.length
              ? `${failures.length} attendee(s) need review. ${failures.slice(0, 5).join(" ")}${failures.length > 5 ? " Use the preview selector to review remaining attendees." : ""}`
              : "All attendee fields fit without overlapping. Download a sample to check the print result.";
    });
    function filter() {
        document
            .querySelectorAll("#attendee-list label")
            .forEach((row) => {
                row.classList.toggle(
                    "hidden",
                    !matchesWords(row.dataset.name, $("attendee-search").value) || !inPrintGroup(row),
                );
            });
        const visible = [
            ...document.querySelectorAll(
                "#attendee-list label:not(.hidden) input",
            ),
        ];
        $("select-all").checked =
            visible.length > 0 && visible.every((x) => x.checked);
        $("select-all").indeterminate =
            visible.some((x) => x.checked) && !$("select-all").checked;
        updatePrint();
    }
    $("attendee-search").addEventListener("input", filter);
    $("print-category").addEventListener("change", filter);
    $("print-gender").addEventListener("change", filter);
    $("print-country").addEventListener("change", filter);
    $("print-printed").addEventListener("change", filter);
    $("attendee-list").addEventListener("change", filter);
    $("print-paper").addEventListener("change", updatePrint);
    $("select-all").addEventListener("change", () => {
        document
            .querySelectorAll("#attendee-list label:not(.hidden) input")
            .forEach((x) => (x.checked = $("select-all").checked));
        filter();
    });
    async function download(sample) {
        if (dirty) return;
        const chosen = ids();
        if (!chosen.length) return;
        const query = {
            paper: $("print-paper").value,
            cut_guides: $("cut-guides").checked,
            sample,
        };
        if (chosen.length !== Object.keys(c.attendees).length)
            query.attendees = chosen;
        $(sample ? "sample-pdf" : "download-pdf").disabled = true;
        $("print-error").textContent = "Preparing PDF…";
        try {
            const queued = !sample && chosen.length > 100;
            const response = await fetch(queued ? c.exportUrl : c.pdfUrl, {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": form.querySelector("[name=_token]").value,
                    Accept: queued ? "application/json" : "application/pdf",
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(query),
            });
            if (queued) {
                if (!response.ok) throw new Error("Could not start the badge export.");
                const started = await response.json();
                $("print-error").textContent = started.message;
                let result;
                do {
                    await new Promise((resolve) => setTimeout(resolve, 2000));
                    const statusResponse = await fetch(started.status_url, { headers: { Accept: "application/json" } });
                    if (!statusResponse.ok) throw new Error("Could not check badge export progress.");
                    result = await statusResponse.json();
                    $("print-error").textContent = result.status === "queued"
                        ? "Badge export is waiting for the queue worker…"
                        : `Preparing badge batch ${result.completed} of ${result.total}…`;
                    if (result.status === "failed") throw new Error(result.error || "Badge export failed.");
                } while (result.status !== "ready");
                showPrinted(chosen, true);
                window.location.assign(result.download_url);
                $("print-error").textContent = "Badge PDF is ready and downloading.";
                return;
            }
            if (response.status === 419)
                throw new Error("Your session has expired. Reload the page and try again.");
            if (response.status === 422)
                throw new Error((await response.json().catch(() => ({}))).message || "No one matches your print selection.");
            if (
                !response.ok ||
                !response.headers
                    .get("content-type")
                    ?.includes("application/pdf")
            )
                throw new Error(
                    "The server could not make this PDF. Try printing fewer badges at a time, and tell your administrator if it keeps happening.",
                );
            const blob = await response.blob(),
                url = URL.createObjectURL(blob),
                link = document.createElement("a");
            link.href = url;
            link.download = sample ? "badge-sample.pdf" : "attendee-badges.pdf";
            if (!sample) showPrinted(chosen, true);
            link.click();
            setTimeout(() => URL.revokeObjectURL(url), 10000);
            $("print-error").textContent = "";
        } catch (error) {
            $("print-error").textContent = error.message;
        } finally {
            updatePrint();
        }
    }
    /** Show these people as printed (or not) in the list, without reloading. */
    function showPrinted(chosen, printed) {
        for (const id of chosen) {
            const row = document.querySelector(`#attendee-list input[value="${id}"]`)?.parentElement;
            if (!row) continue;
            row.dataset.printed = printed ? "yes" : "no";
            row.querySelector(".printed-tag").classList.toggle("hidden", !printed);
            row.querySelector(".printed-tag").title = printed ? "Printed just now" : "";
        }
        filter();
    }
    async function markPrinted(printed) {
        const chosen = ids();
        if (!chosen.length) return;
        if (!printed && !confirm(`Clear the printed record for ${chosen.length} selected? They will show as not printed.`)) return;
        $("print-error").textContent = "Saving…";
        try {
            const response = await fetch(c.printedUrl, {
                method: printed ? "POST" : "DELETE",
                headers: {
                    "X-CSRF-TOKEN": form.querySelector("[name=_token]").value,
                    Accept: "application/json",
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ attendees: chosen }),
            });
            if (response.status === 419)
                throw new Error("Your session has expired. Reload the page and try again.");
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || "Could not save. Try again.");
            showPrinted(chosen, printed);
            $("print-error").textContent = result.message;
        } catch (error) {
            $("print-error").textContent = error.message;
        }
    }
    /** Tick exactly the people on an uploaded list, clearing the filters so all of them show. */
    $("select-list").addEventListener("change", async () => {
        const file = $("select-list").files[0];
        if (!file) return;
        const box = $("list-result");
        box.textContent = "Reading the list…";
        const body = new FormData();
        body.append("file", file);
        try {
            const response = await fetch(c.selectListUrl, {
                method: "POST",
                headers: { "X-CSRF-TOKEN": form.querySelector("[name=_token]").value, Accept: "application/json" },
                body,
            });
            if (response.status === 419)
                throw new Error("Your session has expired. Reload the page and try again.");
            const result = await response.json().catch(() => ({}));
            if (!response.ok)
                throw new Error(Object.values(result.errors || {})[0]?.[0] || result.message || "Could not read that list.");
            for (const id of ["print-category", "print-gender", "print-country", "print-printed"]) $(id).value = "";
            $("attendee-search").value = "";
            const chosen = new Set(result.ids.map(String));
            document.querySelectorAll("#attendee-list input").forEach((x) => (x.checked = chosen.has(x.value)));
            filter();
            box.textContent = `Selected ${result.ids.length} of ${result.rows} on the list.`;
            if (result.problems.length) {
                box.append(` ${result.problems.length} could not be selected:`);
                const list = document.createElement("ul");
                for (const problem of result.problems) {
                    const item = document.createElement("li");
                    item.textContent = `Row ${problem.row}: ${problem.name} - ${problem.reason}`;
                    list.append(item);
                }
                box.append(list);
            }
        } catch (error) {
            box.textContent = error.message;
        } finally {
            $("select-list").value = "";
        }
    });
    $("mark-printed").addEventListener("click", () => markPrinted(true));
    $("unmark-printed").addEventListener("click", () => markPrinted(false));
    $("sample-pdf").addEventListener("click", () => download(true));
    $("download-pdf").addEventListener("click", () => download(false));
    form.addEventListener("submit", () => {
        sync();
        dirty = false;
    });
    window.addEventListener("beforeunload", (event) => {
        if (dirty) {
            event.preventDefault();
            event.returnValue = "";
        }
    });
    if (artwork) {
        const img = new Image();
        img.onload = () => {
            imageSize = [img.naturalWidth, img.naturalHeight];
            render();
        };
        img.src = artwork;
    }
    render();
    filter();
    if (dirty) markDirty();
    document.fonts.ready.then(render);
})();
