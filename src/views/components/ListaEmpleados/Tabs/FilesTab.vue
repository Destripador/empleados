<template>
	<div class="top">
		<div class="file-container" @drop.prevent="handleDrop" @dragover.prevent>
			<input ref="fileInput"
				type="file"
				class="file-input"
				multiple
				@change="uploadFile">

			<div class="file-toolbar">
				<NcButton v-if="navigationStack.length > 0" @click="goBack">
					<template #icon>
						<ArrowLeft :size="20" />
					</template>
				</NcButton>
				<div v-if="showLoading" class="loading-state">
					<NcLoadingIcon :size="25" :message="t('empleados', 'Processing files…')" />
					<p>{{ t('empleados', 'Uploading file, please wait') }}</p>
				</div>
				<NcButton class="open-folder-button" @click="OpenFolder()">
					<template #icon>
						<FolderMoveOutline :size="20" />
					</template>
					{{ t('empleados', 'Open') }}
				</NcButton>
				<NcButton @click="CreateFolder()">
					<template #icon>
						<FolderPlusOutline :size="20" />
					</template>
					{{ t('empleados', 'Create') }}
				</NcButton>
				<NcButton @click="$refs.fileInput.click()">
					<template #icon>
						<CloudUpload :size="20" />
					</template>
					{{ t('empleados', 'Upload') }}
				</NcButton>
				<NcButton @click="reloadFiles()">
					<template #icon>
						<Reload :size="20" />
					</template>
				</NcButton>
			</div>

			<div v-if="files.length > 0" class="file-table-wrap">
				<table class="file-table">
					<thead>
						<tr>
							<th>{{ t('empleados', 'File') }}</th>
							<th>{{ t('empleados', 'Size') }}</th>
							<th>{{ t('empleados', 'Last modified') }}</th>
						</tr>
					</thead>
					<tbody>
						<tr
							v-for="file in files"
							:key="file.id"
							:class="{ 'file-row-folder': file.isFolder }"
							@click="exploreFolder(file)">
							<td>
								<div class="file-item">
									<FolderOutline v-if="file.isFolder" :size="20" />
									<FilePdfBox v-else-if="file.name.endsWith('.pdf')" :size="20" />
									<ImageIcon v-else-if="file.name.match(/\.(jpg|png|jpeg|gif)$/i)" :size="20" />
									<FileOutline v-else :size="20" />
									<span class="file-name">{{ truncateText(file.name) }}</span>
								</div>
							</td>
							<td>{{ file.isFolder ? '-' : formatSize(file.size) }}</td>
							<td>{{ formatDate(file.modified) }}</td>
						</tr>
					</tbody>
				</table>
			</div>

			<p v-if="files.length === 0 && !showLoading" class="empty-msg">
				{{ t('empleados', 'No files available.') }}
			</p>
		</div>

		<NcDialog
			:open.sync="showDialogFolder"
			is-form
			:buttons="buttons"
			:name="t('empleados', 'Create folder')"
			@submit="FolderAction()">
			<NcTextField v-model="Folder" :label="t('empleados', 'New name')" required />
		</NcDialog>
	</div>
</template>

<script>
import FolderPlusOutline from 'vue-material-design-icons/FolderPlusOutline.vue'
import FolderMoveOutline from 'vue-material-design-icons/FolderMoveOutline.vue'
import FolderOutline from 'vue-material-design-icons/FolderOutline.vue'
import CloudUpload from 'vue-material-design-icons/CloudUpload.vue'
import FileOutline from 'vue-material-design-icons/FileOutline.vue'
import FilePdfBox from 'vue-material-design-icons/FilePdfBox.vue'
import ImageIcon from 'vue-material-design-icons/Image.vue'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import Reload from 'vue-material-design-icons/Reload.vue'

import { getClient, defaultRootPath } from '@nextcloud/files/dav'
import { upload as Upload } from '@nextcloud/upload'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import { NcButton, NcDialog, NcTextField, NcLoadingIcon } from '@nextcloud/vue'

import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'FilesTab',
	components: {
		FolderPlusOutline,
		FolderOutline,
		NcButton,
		FileOutline,
		FilePdfBox,
		ImageIcon,
		CloudUpload,
		ArrowLeft,
		FolderMoveOutline,
		NcDialog,
		NcTextField,
		NcLoadingIcon,
		Reload,
	},
	props: {
		data: { type: Object, required: true },
		show: { type: Boolean, required: true },
		empleados: { type: Array, required: true },
	},
	data() {
		return {
			files: [],
			currentPath: null,
			navigationStack: [],
			client: null,
			showDialogFolder: false,
			showLoading: false,
			Folder: '',
			buttons: [{ label: t('empleados', 'Crear'), type: 'primary', nativeType: 'submit' }],
		}
	},
	watch: {
		data(newData) {
			if (newData) {
				const nombre = newData.displayname?.trim() || newData.uid
				this.currentPath = `${defaultRootPath}/EMPLEADOS/${newData.uid} - ${nombre.toUpperCase()}/`
				this.navigationStack = []
				this.fetchFiles()
			}
		},
	},
	mounted() {
		const nombre = this.data.displayname?.trim() || this.data.uid
		this.currentPath = `${defaultRootPath}/EMPLEADOS/${this.data.uid} - ${nombre.toUpperCase()}/`
		this.fetchFiles()
	},
	methods: {
		async fetchFiles() {
			try {
				this.client = getClient()
				const response = await this.client.getDirectoryContents(this.currentPath, { details: true })
				this.files = Array.isArray(response.data)
					? response.data.map(file => ({
						id: file.id || file.etag,
						name: file.basename || file.name,
						size: file.size || 0,
						isFolder: file.type === 'directory',
						location: this.currentPath,
						modified: file.lastmod || null,
					}))
					: []
			} catch (error) {
				showError('❌ Error al obtener archivos: ' + error.message)
			}
		},
		exploreFolder(file) {
			if (file.isFolder) {
				this.navigationStack.push(this.currentPath)
				this.currentPath += `${file.name}/`
				this.fetchFiles()
			}
		},
		goBack() {
			if (this.navigationStack.length) {
				this.currentPath = this.navigationStack.pop()
				this.fetchFiles()
			}
		},
		CreateFolder() {
			this.showDialogFolder = true
		},
		async FolderAction() {
			try {
				const safeFolder = this.Folder.trim().replace(/[<>:"/\\|?*]/g, '_')
				await getClient().createDirectory(`${this.currentPath}${safeFolder}/`)
				this.Folder = ''
				showSuccess('✅ Carpeta creada.')
				this.fetchFiles()
			} catch (e) {
				showError('❌ ' + e.message)
			}
		},
		async subirArchivo(file, destino) {
			const start = performance.now()
			await Upload(destino, file)
			const duracion = (performance.now() - start) / 1000
			const velocidad = file.size / duracion
			let delay = Math.min((file.size / velocidad) * 1.5 * 1000, 5000)
			delay = Math.max(delay, 500)
			for (let i = 0; i < 5; i++) {
				try {
					const remoto = await this.client.stat(this.currentPath + file.name)
					if (remoto?.size === file.size) {
						showSuccess(`✅ Archivo ${file.name} subido correctamente.`)
						return
					}
				} catch {}
				await new Promise(resolve => setTimeout(resolve, delay))
			}
			showWarning(`⚠️ No se pudo confirmar la subida del archivo ${file.name} después de varios intentos.`)
		},
		async uploadFile(event) {
			const files = Array.from(event.target.files)
			if (!files.length) return
			this.showLoading = true
			const destinoBase = this.currentPath.replace(/^\/files\/[^/]+/, '')
			for (const file of files) {
				await this.subirArchivo(file, destinoBase + file.name)
			}
			await this.fetchFiles()
			this.showLoading = false
		},
		async handleDrop(event) {
			const files = Array.from(event.dataTransfer.files)
			if (!files.length) return
			this.showLoading = true
			const destinoBase = this.currentPath.replace(/^\/files\/[^/]+/, '')
			for (const file of files) {
				await this.subirArchivo(file, destinoBase + file.name)
			}
			await this.fetchFiles()
			this.showLoading = false
		},
		truncateText(text, length = 75) {
			return text?.length > length ? text.substring(0, length) + '...' : text
		},
		formatSize(bytes) {
			if (!bytes) return '-'
			if (bytes < 1024) return `${bytes} B`
			const kb = bytes / 1024
			if (kb < 1024) return `${kb.toFixed(2)} KB`
			return `${(kb / 1024).toFixed(2)} MB`
		},
		formatDate(dateString) {
			if (!dateString) return '-'
			return new Date(dateString).toLocaleString('es-MX')
		},
		OpenFolder() {
			const url = `${window.location.origin}/apps/files?dir=${encodeURIComponent(this.getCleanPath(this.currentPath))}`
			window.open(url, '_blank')
		},
		getCleanPath(path) {
			const seg = path.split('/').filter(Boolean)
			return seg.length > 2 ? '/' + seg.slice(2).join('/') + '/' : path
		},
		reloadFiles() {
			this.fetchFiles()
		},
	},
}
</script>

<style scoped>
.top {
	margin-top: 14px;
}

.file-container {
	margin: 0 auto;
	padding: 18px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
	min-height: 200px;
	text-align: center;
	transition: border-color 120ms ease, box-shadow 120ms ease;
}

.file-container:focus-within {
	border-color: var(--color-primary-element-light);
	box-shadow: 0 0 0 2px var(--color-primary-element-light);
}

.file-toolbar {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: flex-start;
	gap: 10px;
	margin-bottom: 16px;
}

.open-folder-button {
	margin-left: auto;
}

.loading-state {
	display: inline-flex;
	align-items: center;
	min-height: 44px;
	gap: 8px;
	padding: 0 10px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
}

.loading-state p {
	margin: 0;
	font-size: 13px;
	font-weight: 600;
}

.file-input {
	display: none;
}

.file-table-wrap {
	overflow-x: auto;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.file-table {
	width: 100%;
	border-collapse: collapse;
	table-layout: fixed;
}

.file-table th,
.file-table td {
	padding: 12px 14px;
	border-bottom: 1px solid var(--color-border);
	color: var(--color-main-text);
	text-align: left;
	vertical-align: middle;
}

.file-table th {
	background-color: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
}

.file-table th:first-child,
.file-table td:first-child {
	width: 58%;
}

.file-table th:nth-child(2),
.file-table td:nth-child(2) {
	width: 16%;
	white-space: nowrap;
}

.file-table th:nth-child(3),
.file-table td:nth-child(3) {
	width: 26%;
	white-space: nowrap;
}

.file-table tbody tr {
	cursor: default;
	transition: background-color 120ms ease;
}

.file-table tbody tr.file-row-folder {
	cursor: pointer;
}

.file-table tbody tr:last-child td {
	border-bottom: 0;
}

.file-table tr:hover {
	background-color: var(--color-background-hover);
}

.empty-msg {
	margin: 44px 0;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
	font-weight: 600;
	text-align: center;
}

.file-item {
	display: flex;
	align-items: center;
	gap: 8px;
	min-width: 0;
	color: var(--color-primary-element);
}

.file-name {
	min-width: 0;
	overflow: hidden;
	color: var(--color-main-text);
	font-weight: 600;
	text-overflow: ellipsis;
	white-space: nowrap;
}

@media (max-width: 768px) {
	.file-container {
		padding: 12px;
	}

	.file-toolbar {
		gap: 8px;
	}

	.open-folder-button {
		margin-left: 0;
	}

	.loading-state {
		order: 10;
		width: 100%;
		justify-content: center;
	}

	.file-table {
		min-width: 620px;
	}
}
</style>
