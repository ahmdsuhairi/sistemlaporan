/**
 * Enjin Carta SVG Vanilla JS (bebas kebergantungan luar / serasi intranet & PWA).
 * Menyokong carta garis prestasi dengan gradien & label nilai, carta bar berbilang warna,
 * serta carta donat dengan jumlah di tengah.
 */
(function (global) {
    'use strict';

    const SVG_NS = 'http://www.w3.org/2000/svg';
    let tooltipEl = null;

    function getTooltip() {
        if (!tooltipEl) {
            tooltipEl = document.createElement('div');
            tooltipEl.className = 'chart-tooltip';
            document.body.appendChild(tooltipEl);
        }
        return tooltipEl;
    }

    function showTooltip(evt, html) {
        const tip = getTooltip();
        tip.innerHTML = html;
        tip.style.opacity = '1';
        const pad = 14;
        const left = Math.min(window.innerWidth - 270, evt.clientX + pad);
        const top = Math.max(10, evt.clientY - 46);
        tip.style.left = left + 'px';
        tip.style.top = top + 'px';
    }

    function hideTooltip() {
        if (tooltipEl) {
            tooltipEl.style.opacity = '0';
        }
    }

    function el(tag, attrs = {}, text = null) {
        const node = document.createElementNS(SVG_NS, tag);
        for (const [k, v] of Object.entries(attrs)) {
            node.setAttribute(k, String(v));
        }
        if (text !== null) {
            node.textContent = text;
        }
        return node;
    }

    function fmtRM(val) {
        const num = Number(val) || 0;
        return 'RM ' + num.toLocaleString('en-MY', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        });
    }

    function niceMax(val) {
        if (val <= 0) return 100;
        const mag = Math.pow(10, Math.floor(Math.log10(val)));
        const norm = val / mag;
        if (norm <= 1.2) return 1.2 * mag;
        if (norm <= 1.5) return 1.5 * mag;
        if (norm <= 2) return 2 * mag;
        if (norm <= 3) return 3 * mag;
        if (norm <= 5) return 5 * mag;
        return 10 * mag;
    }

    /**
     * Carta Garis + Kawasan Gradien menyerupai kad Prestasi Subjek dalam PDF.
     */
    function renderPrestasiLineChart(container, config) {
        container.innerHTML = '';
        const items = config.items || [];
        if (items.length === 0) {
            container.innerHTML = '<div class="empty-state">Tiada data siri untuk dipaparkan.</div>';
            return;
        }

        const w = 560;
        const h = 235;
        const padL = 58;
        const padR = 24;
        const padT = 26;
        const padB = 46;
        const plotW = w - padL - padR;
        const plotH = h - padT - padB;

        const color = config.color || '#4f46e5';
        const gradId = 'grad_' + Math.random().toString(36).slice(2, 9);
        const valueKey = config.valueKey || 'baki';
        const maxVal = niceMax(Math.max(...items.map(d => Math.max(0, Number(d[valueKey]) || 0)), 10));

        const svg = el('svg', { viewBox: `0 0 ${w} ${h}`, class: 'chart-svg' });

        // Definisi gradien
        const defs = el('defs');
        const grad = el('linearGradient', { id: gradId, x1: '0', y1: '0', x2: '0', y2: '1' });
        grad.appendChild(el('stop', { offset: '0%', 'stop-color': color, 'stop-opacity': '0.26' }));
        grad.appendChild(el('stop', { offset: '100%', 'stop-color': color, 'stop-opacity': '0.02' }));
        defs.appendChild(grad);
        svg.appendChild(defs);

        // Garis grid mengufuk & label paksi Y
        const steps = 4;
        for (let i = 0; i <= steps; i++) {
            const ratio = i / steps;
            const y = padT + plotH - ratio * plotH;
            const tickVal = Math.round(maxVal * ratio);
            svg.appendChild(el('line', {
                x1: padL, y1: y, x2: w - padR, y2: y,
                stroke: 'var(--chart-grid)',
                'stroke-width': 1,
                'stroke-dasharray': i === 0 ? '' : '3 3'
            }));
            svg.appendChild(el('text', {
                x: padL - 8, y: y + 4,
                'text-anchor': 'end',
                fill: 'var(--chart-label)',
                'font-size': 10.5
            }, tickVal >= 1000 ? (tickVal / 1000).toFixed(1).replace(/\.0$/, '') + 'k' : String(tickVal)));
        }

        const n = items.length;
        const points = items.map((item, idx) => {
            const x = n === 1 ? padL + plotW / 2 : padL + (idx / (n - 1)) * plotW;
            const raw = Math.max(0, Number(item[valueKey]) || 0);
            const y = padT + plotH - (raw / maxVal) * plotH;
            return { x, y, raw, item };
        });

        // Poligon kawasan gradien
        if (points.length >= 2) {
            const areaD = [
                `M ${points[0].x} ${padT + plotH}`,
                ...points.map(p => `L ${p.x} ${p.y}`),
                `L ${points[points.length - 1].x} ${padT + plotH}`,
                'Z'
            ].join(' ');
            svg.appendChild(el('path', { d: areaD, fill: `url(#${gradId})` }));

            const lineD = points.map((p, idx) => `${idx === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
            svg.appendChild(el('path', {
                d: lineD,
                fill: 'none',
                stroke: color,
                'stroke-width': 2.6,
                'stroke-linecap': 'round',
                'stroke-linejoin': 'round'
            }));
        }

        // Titik, label nilai di atas titik, & label paksi X
        points.forEach((p, idx) => {
            const shortNum = p.raw >= 1000
                ? (p.raw / 1000).toFixed(1).replace(/\.0$/, '') + 'k'
                : String(Math.round(p.raw));

            svg.appendChild(el('text', {
                x: p.x,
                y: Math.max(13, p.y - 10),
                'text-anchor': 'middle',
                fill: color,
                'font-size': 10.5,
                'font-weight': 700
            }, shortNum));

            const dot = el('circle', {
                cx: p.x,
                cy: p.y,
                r: 4.5,
                fill: color,
                stroke: 'var(--bg-surface)',
                'stroke-width': 2,
                style: 'cursor:pointer;'
            });

            dot.addEventListener('mousemove', (evt) => {
                showTooltip(evt, `
                    <strong>${escapeHtml(p.item.full_label || p.item.label)}</strong><br>
                    Bil: ${fmtRM(p.item[config.bilKey])}<br>
                    Terimaan: ${fmtRM(p.item[config.terimaKey])}<br>
                    Baki: ${fmtRM(p.item[config.bakiKey])}<br>
                    Kutipan: ${Number(p.item[config.pctKey] || 0).toFixed(1)}%
                `);
            });
            dot.addEventListener('mouseleave', hideTooltip);
            svg.appendChild(dot);

            // Paksi X
            const lbl = String(p.item.label || '');
            const compactLbl = lbl.length > 11 ? lbl.slice(0, 10) + '…' : lbl;
            const textNode = el('text', {
                x: p.x,
                y: h - 18,
                'text-anchor': 'middle',
                fill: 'var(--chart-label)',
                'font-size': 10
            }, compactLbl);
            if (n > 6 && idx % 2 === 1) {
                textNode.setAttribute('y', String(h - 6));
            }
            svg.appendChild(textNode);
        });

        container.appendChild(svg);
    }

    /**
     * Carta Bar berbilang warna menyerupai carta Analisis Peperiksaan dalam PDF.
     */
    function renderBarChart(container, buckets) {
        container.innerHTML = '';
        if (!buckets || buckets.length === 0) {
            container.innerHTML = '<div class="empty-state">Tiada data taburan.</div>';
            return;
        }

        const w = 520;
        const h = 250;
        const padL = 44;
        const padR = 16;
        const padT = 26;
        const padB = 46;
        const plotW = w - padL - padR;
        const plotH = h - padT - padB;

        const maxCount = niceMax(Math.max(...buckets.map(b => Number(b.count) || 0), 4));
        const svg = el('svg', { viewBox: `0 0 ${w} ${h}`, class: 'chart-svg' });

        for (let i = 0; i <= 4; i++) {
            const ratio = i / 4;
            const y = padT + plotH - ratio * plotH;
            svg.appendChild(el('line', {
                x1: padL, y1: y, x2: w - padR, y2: y,
                stroke: 'var(--chart-grid)',
                'stroke-width': 1,
                'stroke-dasharray': i === 0 ? '' : '3 3'
            }));
            svg.appendChild(el('text', {
                x: padL - 7, y: y + 4,
                'text-anchor': 'end',
                fill: 'var(--chart-label)',
                'font-size': 10.5
            }, String(Math.round(maxCount * ratio))));
        }

        const step = plotW / buckets.length;
        const barW = Math.min(44, step * 0.56);

        buckets.forEach((b, idx) => {
            const cnt = Number(b.count) || 0;
            const barH = Math.max(2, (cnt / maxCount) * plotH);
            const x = padL + idx * step + (step - barW) / 2;
            const y = padT + plotH - barH;

            const rect = el('rect', {
                x, y,
                width: barW,
                height: barH,
                rx: 6,
                fill: b.color || '#4f46e5',
                style: 'cursor:pointer;'
            });
            rect.addEventListener('mousemove', (evt) => {
                showTooltip(evt, `<strong>${escapeHtml(b.label)}</strong><br>Bilangan Akaun: ${cnt.toLocaleString()}`);
            });
            rect.addEventListener('mouseleave', hideTooltip);
            svg.appendChild(rect);

            svg.appendChild(el('text', {
                x: x + barW / 2,
                y: y - 6,
                'text-anchor': 'middle',
                fill: 'var(--text-primary)',
                'font-size': 11,
                'font-weight': 700
            }, String(cnt)));

            svg.appendChild(el('text', {
                x: x + barW / 2,
                y: h - 18,
                'text-anchor': 'middle',
                fill: 'var(--chart-label)',
                'font-size': 10
            }, b.label));
        });

        container.appendChild(svg);
    }

    /**
     * Carta Donat Nisbah Akaun Selesai vs Tertunggak dengan jumlah di tengah.
     */
    function renderDonutChart(container, stats) {
        container.innerHTML = '';
        const clean = Number(stats.cleanCount) || 0;
        const arrears = Number(stats.arrearsCount) || 0;
        const total = clean + arrears;

        const w = 280;
        const h = 215;
        const cx = w / 2;
        const cy = h / 2;
        const r = 72;
        const circ = 2 * Math.PI * r;

        const svg = el('svg', { viewBox: `0 0 ${w} ${h}`, class: 'chart-svg', style: 'max-width:290px;margin:0 auto;' });

        if (total === 0) {
            svg.appendChild(el('circle', {
                cx, cy, r,
                fill: 'none',
                stroke: 'var(--chart-grid)',
                'stroke-width': 24
            }));
        } else {
            const cleanLen = (clean / total) * circ;
            const arrearsLen = (arrears / total) * circ;

            const c1 = el('circle', {
                cx, cy, r,
                fill: 'none',
                stroke: '#10b981',
                'stroke-width': 24,
                'stroke-dasharray': `${cleanLen} ${circ}`,
                transform: `rotate(-90 ${cx} ${cy})`,
                style: 'cursor:pointer;'
            });
            c1.addEventListener('mousemove', (evt) => {
                const pct = ((clean / total) * 100).toFixed(1);
                showTooltip(evt, `<strong>Selesai / Bersih</strong><br>${clean.toLocaleString()} akaun (${pct}%)`);
            });
            c1.addEventListener('mouseleave', hideTooltip);

            const c2 = el('circle', {
                cx, cy, r,
                fill: 'none',
                stroke: '#ef4444',
                'stroke-width': 24,
                'stroke-dasharray': `${arrearsLen} ${circ}`,
                'stroke-dashoffset': -cleanLen,
                transform: `rotate(-90 ${cx} ${cy})`,
                style: 'cursor:pointer;'
            });
            c2.addEventListener('mousemove', (evt) => {
                const pct = ((arrears / total) * 100).toFixed(1);
                showTooltip(evt, `<strong>Tertunggak</strong><br>${arrears.toLocaleString()} akaun (${pct}%)`);
            });
            c2.addEventListener('mouseleave', hideTooltip);

            svg.appendChild(c1);
            svg.appendChild(c2);
        }

        svg.appendChild(el('text', {
            x: cx, y: cy - 4,
            'text-anchor': 'middle',
            fill: 'var(--text-primary)',
            'font-size': 26,
            'font-weight': 800
        }, total.toLocaleString()));

        svg.appendChild(el('text', {
            x: cx, y: cy + 16,
            'text-anchor': 'middle',
            fill: 'var(--text-muted)',
            'font-size': 11,
            'font-weight': 600
        }, 'JUMLAH AKAUN'));

        container.appendChild(svg);
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    global.DashboardCharts = {
        renderPrestasiLineChart,
        renderBarChart,
        renderDonutChart
    };
})(window);

