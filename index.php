<?php
/**
 * Manajemen Pengguna Sistem
 * Smart Parking Management System
 */
$page_title = 'Kelola Pengguna';
$active_menu = 'user';
require_once __DIR__ . '/../../includes/header.php';
check_role(['admin']);

$stmt = $pdo->query("SELECT * FROM tb_user ORDER BY id_user ASC");
$users = $stmt->fetchAll();
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-people-fill text-primary me-2"></i>Kelola Pengguna & Petugas</h4>
        <p class="text-muted small mb-0">Atur hak akses login petugas loket, administrator, dan pemilik manajemen.</p>
    </div>
    <button type="button" class="btn btn-primary-gradient btn-rounded" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
        <i class="bi bi-person-plus-fill me-1"></i> Tambah Pengguna
    </button>
</div>

<div class="card card-custom">
    <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0">Daftar Akun: <span class="badge bg-primary rounded-pill"><?= count($users) ?> Akun</span></h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">ID #</th>
                        <th>Nama Lengkap</th>
                        <th>Username</th>
                        <th>Role / Peran</th>
                        <th>Shift</th>
                        <th>Status Akun</th>
                        <th class="text-center pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): 
                        $roleBadge = $u['role'] === 'admin' ? 'bg-primary' : ($u['role'] === 'petugas' ? 'bg-success' : 'bg-warning text-dark');
                    ?>
                    <tr>
                        <td class="ps-3 text-muted">#<?= $u['id_user'] ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px;">
                                    <?= strtoupper(substr($u['nama_lengkap'], 0, 1)) ?>
                                </div>
                                <div>
                                    <strong class="d-block text-dark"><?= htmlspecialchars($u['nama_lengkap']) ?></strong>
                                    <?php if ($u['id_user'] == $user['id_user']): ?>
                                        <small class="badge bg-primary-subtle text-primary border border-primary-subtle">Akun Anda Saat Ini</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><code class="text-primary fw-bold">@<?= htmlspecialchars($u['username']) ?></code></td>
                        <td>
                            <span class="badge <?= $roleBadge ?> text-uppercase px-2 py-1"><?= htmlspecialchars($u['role']) ?></span>
                        </td>
                        <td>
                            <?php if ($u['role'] === 'petugas'): ?>
                                <span class="badge <?= $u['shift_kerja'] === 'malam' ? 'text-bg-primary' : 'text-bg-warning' ?> text-uppercase"><?= htmlspecialchars($u['shift_kerja'] ?? 'belum diatur') ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['status_aktif'] == 1): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="bi bi-check-circle-fill me-1"></i> Aktif
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                    <i class="bi bi-x-circle-fill me-1"></i> Dinonaktifkan
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center pe-3">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-warning" title="Edit Akun" onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if ($u['id_user'] != $user['id_user']): ?>
                                <button class="btn btn-outline-danger" title="Hapus Akun" onclick="confirmAction('<?= base_url('modules/user/proses.php?action=delete&id=' . $u['id_user']) ?>', 'Hapus Pengguna?', 'Akun @<?= htmlspecialchars($u['username']) ?> akan dihapus secara permanen.')">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah User -->
<div class="modal fade" id="modalTambahUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <form action="<?= base_url('modules/user/proses.php?action=create') ?>" method="POST">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill text-primary me-2"></i>Tambah Pengguna Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama_lengkap" class="form-control" placeholder="Contoh: Rian Anggara" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" placeholder="Contoh: rian_petugas" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fw-bold small">Role / Peran <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="petugas">Petugas Loket</option>
                                <option value="admin">Administrator</option>
                                <option value="owner">Owner / Manajemen</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small">Status Akun</label>
                            <select name="status_aktif" class="form-select">
                                <option value="1">Aktif</option>
                                <option value="0">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label fw-bold small">Shift Petugas</label>
                        <select name="shift_kerja" class="form-select">
                            <option value="">Tidak ditetapkan</option>
                            <option value="pagi">Shift Pagi</option>
                            <option value="malam">Shift Malam</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-gradient px-4">Simpan Akun</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit User -->
<div class="modal fade" id="modalEditUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <form action="<?= base_url('modules/user/proses.php?action=update') ?>" method="POST">
                <input type="hidden" name="id_user" id="editIdUser">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Pengguna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama_lengkap" id="editNamaLengkap" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="editUsername" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Ubah Password Baru</label>
                        <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fw-bold small">Role / Peran <span class="text-danger">*</span></label>
                            <select name="role" id="editRole" class="form-select" required>
                                <option value="petugas">Petugas Loket</option>
                                <option value="admin">Administrator</option>
                                <option value="owner">Owner / Manajemen</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small">Status Akun</label>
                            <select name="status_aktif" id="editStatusAktif" class="form-select">
                                <option value="1">Aktif</option>
                                <option value="0">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label fw-bold small">Shift Petugas</label>
                        <select name="shift_kerja" id="editShiftKerja" class="form-select">
                            <option value="">Tidak ditetapkan</option>
                            <option value="pagi">Shift Pagi</option>
                            <option value="malam">Shift Malam</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-gradient px-4">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editUser(u) {
    document.getElementById('editIdUser').value = u.id_user;
    document.getElementById('editNamaLengkap').value = u.nama_lengkap;
    document.getElementById('editUsername').value = u.username;
    document.getElementById('editRole').value = u.role;
    document.getElementById('editStatusAktif').value = u.status_aktif;
    document.getElementById('editShiftKerja').value = u.shift_kerja || '';
    new bootstrap.Modal(document.getElementById('modalEditUser')).show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
