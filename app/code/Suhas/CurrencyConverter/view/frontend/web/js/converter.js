/**
 * Copyright © Suhas. All rights reserved.
 *
 * Storefront currency converter widget.
 *
 * Initialised via data-mage-init with a config object of the shape:
 *   { endpoints: { currencies, rate, history }, defaults: { from, to, historyDays } }
 */
define(['jquery', 'chartJs', 'mage/translate'], function ($, Chart, $t) {
    'use strict';

    return function (config, element) {
        var $root = $(element),
            $amount = $root.find('[data-role="amount"]'),
            $from = $root.find('[data-role="from"]'),
            $to = $root.find('[data-role="to"]'),
            $swap = $root.find('[data-role="swap"]'),
            $loading = $root.find('[data-role="loading"]'),
            $resultBody = $root.find('[data-role="result-body"]'),
            $converted = $root.find('[data-role="converted"]'),
            $rateLine = $root.find('[data-role="rate-line"]'),
            $rateDate = $root.find('[data-role="rate-date"]'),
            $error = $root.find('[data-role="error"]'),
            $chartCanvas = $root.find('[data-role="chart"]'),
            chart = null,
            latestRate = null,
            requestToken = 0;

        /**
         * Show an error banner and clear any stale result.
         */
        function showError(message) {
            $error.text(message).prop('hidden', false);
        }

        function clearError() {
            $error.text('').prop('hidden', true);
        }

        function setBusy(isBusy) {
            $loading.prop('hidden', !isBusy);
            if (isBusy) {
                $resultBody.prop('hidden', true);
            }
        }

        /**
         * Populate a <select> with the currency list and select `selected`.
         */
        function fillSelect($select, currencies, selected) {
            $select.empty();

            // Build options via jQuery text()/val() rather than HTML concatenation so
            // currency names from the API can never inject markup into the page.
            currencies.forEach(function (currency) {
                $select.append(
                    $('<option>').val(currency.code).text(currency.code + ' – ' + currency.name)
                );
            });

            $select.val(selected).prop('disabled', false);

            // Fall back to the first option if the configured default is unavailable.
            if ($select.val() !== selected) {
                $select.prop('selectedIndex', 0);
            }
        }

        function formatNumber(value, decimals) {
            return Number(value).toLocaleString(undefined, {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        }

        /**
         * Recompute and render the converted amount from the last fetched rate.
         */
        function renderConversion() {
            if (latestRate === null) {
                return;
            }

            var amount = parseFloat($amount.val());

            if (isNaN(amount) || amount < 0) {
                amount = 0;
            }

            var converted = amount * latestRate.rate;

            $converted.text(
                formatNumber(amount, 2) + ' ' + latestRate.base + ' = ' +
                formatNumber(converted, 4) + ' ' + latestRate.quote
            );
            $rateLine.text('1 ' + latestRate.base + ' = ' + formatNumber(latestRate.rate, 6) + ' ' + latestRate.quote);
            $rateDate.text($t('Rate as of %1').replace('%1', latestRate.date));
            $resultBody.prop('hidden', false);
        }

        /**
         * Draw (or update) the historical line chart.
         */
        function renderChart(history) {
            var labels = history.series.map(function (point) {
                    return point.date;
                }),
                data = history.series.map(function (point) {
                    return point.rate;
                }),
                label = history.base + ' → ' + history.quote;

            if (chart) {
                chart.data.labels = labels;
                chart.data.datasets[0].label = label;
                chart.data.datasets[0].data = data;
                chart.update();
                return;
            }

            chart = new Chart($chartCanvas[0].getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: label,
                        data: data,
                        borderColor: '#1979c3',
                        backgroundColor: 'rgba(25, 121, 195, 0.1)',
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        tension: 0.15,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: { ticks: { maxTicksLimit: 8, autoSkip: true } },
                        y: { beginAtZero: false }
                    },
                    plugins: {
                        legend: { display: true },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    return formatNumber(ctx.parsed.y, 6);
                                }
                            }
                        }
                    }
                }
            });
        }

        /**
         * Fetch the current rate and the historical series for the current pair.
         */
        function refresh() {
            var from = $from.val(),
                to = $to.val(),
                token = ++requestToken;

            if (!from || !to) {
                return;
            }

            clearError();
            setBusy(true);

            var ratePromise = $.ajax({
                url: config.endpoints.rate,
                method: 'GET',
                dataType: 'json',
                data: { from: from, to: to }
            });

            var historyPromise = $.ajax({
                url: config.endpoints.history,
                method: 'GET',
                dataType: 'json',
                data: { from: from, to: to, days: config.defaults.historyDays }
            });

            $.when(ratePromise, historyPromise)
                .done(function (rateResp, historyResp) {
                    // Ignore responses from superseded requests (fast switching).
                    if (token !== requestToken) {
                        return;
                    }

                    var rateData = rateResp[0],
                        historyData = historyResp[0];

                    setBusy(false);

                    if (!rateData.success) {
                        showError(rateData.message || $t('Unable to load the exchange rate.'));
                        return;
                    }

                    latestRate = rateData.rate;
                    renderConversion();

                    if (historyData.success && historyData.history.series.length) {
                        renderChart(historyData.history);
                    }
                })
                .fail(function () {
                    if (token !== requestToken) {
                        return;
                    }
                    setBusy(false);
                    showError($t('Unable to reach the exchange-rate service. Please try again later.'));
                });
        }

        /**
         * Swap the From and To selections, then refresh.
         */
        function swap() {
            var fromVal = $from.val(),
                toVal = $to.val();

            $from.val(toVal);
            $to.val(fromVal);
            refresh();
        }

        /**
         * Load the currency list, wire events, and do the first lookup.
         */
        function init() {
            $.ajax({
                url: config.endpoints.currencies,
                method: 'GET',
                dataType: 'json'
            }).done(function (response) {
                if (!response.success || !response.currencies.length) {
                    showError(response.message || $t('Unable to load the list of currencies.'));
                    return;
                }

                fillSelect($from, response.currencies, config.defaults.from);
                fillSelect($to, response.currencies, config.defaults.to);
                $swap.prop('disabled', false);

                $from.on('change', refresh);
                $to.on('change', refresh);
                $amount.on('input', renderConversion);
                $swap.on('click', swap);

                refresh();
            }).fail(function () {
                showError($t('Unable to load the list of currencies. Please try again later.'));
            });
        }

        init();
    };
});
