    </main>

    <footer class="content-footer">
        <div class="d-flex justify-content-between align-items-center">
            <span><?= sanitize($companyName) ?> &copy; <?= date('Y') ?></span>
            <span class="text-muted small">v<?= VERSION ?></span>
        </div>
    </footer>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/tr.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<?php if (isset($extraJs)) echo $extraJs; ?>
</body>
</html>
