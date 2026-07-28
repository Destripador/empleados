<template>
	<section class="employee-files" :aria-busy="isBusy ? 'true' : 'false'">
		<input ref="fileInput"
			type="file"
			class="hidden-file-input"
			multiple
			@change="uploadFile">

		<header class="files-header">
			<div>
				<h2>{{ t('empleados', 'Employee files') }}</h2>
				<p>{{ employeeName }}</p>
			</div>
			<NcButton type="tertiary"
				:aria-label="t('empleados', 'Open folder in Nextcloud Files')"
				:title="t('empleados', 'Open folder in Nextcloud Files')"
				@click="openInFiles">
				<template #icon>
					<OpenInNew :size="20" />
				</template>
				{{ t('empleados', 'Open in Files') }}
			</NcButton>
		</header>

		<div class="navigation-bar">
			<div class="navigation-controls">
				<NcButton type="tertiary"
					:disabled="isAtRoot || isBusy"
					:aria-label="t('empleados', 'Go back')"
					:title="t('empleados', 'Go back')"
					@click="goBack">
					<template #icon>
						<ArrowLeft :size="20" />
					</template>
				</NcButton>
				<NcButton type="tertiary"
					:disabled="isAtRoot || isBusy"
					:aria-label="t('empleados', 'Go to employee folder')"
					:title="t('empleados', 'Go to employee folder')"
					@click="goHome">
					<template #icon>
						<HomeOutline :size="20" />
					</template>
				</NcButton>
			</div>

			<nav class="breadcrumbs" :aria-label="t('empleados', 'Current folder')">
				<button v-for="(crumb, index) in breadcrumbs"
					:key="crumb.path"
					type="button"
					class="breadcrumb"
					:class="{ current: index === breadcrumbs.length - 1 }"
					:aria-current="index === breadcrumbs.length - 1 ? 'page' : null"
					:disabled="isBusy"
					@click="navigateTo(crumb.path)">
					<span>{{ crumb.label }}</span>
					<ChevronRight v-if="index < breadcrumbs.length - 1" :size="16" />
				</button>
			</nav>
		</div>

		<div class="file-toolbar">
			<div class="primary-actions">
				<NcButton type="primary" :disabled="isBusy" @click="openCreateFolderDialog">
					<template #icon>
						<FolderPlusOutline :size="20" />
					</template>
					{{ t('empleados', 'New folder') }}
				</NcButton>
				<NcButton :disabled="isBusy" @click="chooseFiles">
					<template #icon>
						<CloudUpload :size="20" />
					</template>
					{{ t('empleados', 'Upload files') }}
				</NcButton>
				<NcButton type="tertiary"
					:disabled="isBusy"
					:aria-label="t('empleados', 'Refresh files')"
					:title="t('empleados', 'Refresh files')"
					@click="fetchFiles()">
					<template #icon>
						<Reload :size="20" />
					</template>
				</NcButton>
			</div>

			<NcTextField :value.sync="searchQuery"
				class="files-search"
				:label="t('empleados', 'Search in this folder')"
				:show-trailing-button="Boolean(searchQuery)"
				@trailing-button-click="searchQuery = ''">
				<template #icon>
					<Magnify :size="20" />
				</template>
			</NcTextField>
		</div>

		<div v-if="isUploading" class="upload-status" role="status">
			<NcLoadingIcon :size="24" />
			<div>
				<strong>{{ t('empleados', 'Uploading {done} of {total}', uploadProgress) }}</strong>
				<span>{{ uploadProgress.current }}</span>
			</div>
			<div class="progress-track" aria-hidden="true">
				<span :style="{ width: uploadPercentage + '%' }" />
			</div>
		</div>

		<div class="folder-summary" aria-live="polite">
			<span>{{ folderSummary }}</span>
			<span v-if="searchQuery">
				{{ t('empleados', '{count} results', { count: visibleFiles.length }) }}
			</span>
		</div>

		<div class="browser-surface"
			:class="{ 'is-dragging': dragActive }"
			@dragenter.prevent="dragActive = true"
			@dragover.prevent="dragActive = true"
			@dragleave.prevent="handleDragLeave"
			@drop.prevent="handleDrop">
			<div v-if="dragActive" class="drop-overlay">
				<CloudUpload :size="44" />
				<strong>{{ t('empleados', 'Drop files here to upload them') }}</strong>
				<span>{{ t('empleados', 'They will be saved in the current folder.') }}</span>
			</div>

			<div v-if="isLoading" class="central-state" role="status">
				<NcLoadingIcon :size="36" :message="t('empleados', 'Loading files…')" />
			</div>

			<div v-else-if="loadError" class="central-state error-state" role="alert">
				<InformationOutline :size="44" />
				<strong>{{ t('empleados', 'The files could not be loaded') }}</strong>
				<span>{{ loadError }}</span>
				<NcButton @click="fetchFiles()">
					{{ t('empleados', 'Try again') }}
				</NcButton>
			</div>

			<div v-else-if="visibleFiles.length" class="file-table-wrap">
				<table class="file-table">
					<thead>
						<tr>
							<th>
								<button type="button" class="sort-button" @click="setSort('name')">
									{{ t('empleados', 'Name') }}
									<component :is="sortIcon('name')" v-if="sortKey === 'name'" :size="16" />
								</button>
							</th>
							<th>
								<button type="button" class="sort-button" @click="setSort('size')">
									{{ t('empleados', 'Size') }}
									<component :is="sortIcon('size')" v-if="sortKey === 'size'" :size="16" />
								</button>
							</th>
							<th>
								<button type="button" class="sort-button" @click="setSort('modified')">
									{{ t('empleados', 'Modified') }}
									<component :is="sortIcon('modified')" v-if="sortKey === 'modified'" :size="16" />
								</button>
							</th>
							<th class="actions-heading">
								<span class="hidden-visually">{{ t('empleados', 'Actions') }}</span>
							</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="file in visibleFiles"
							:key="file.id"
							:class="{ 'folder-row': file.isFolder }">
							<td>
								<button type="button"
									class="file-identity"
									:title="file.name"
									@click="openItem(file)">
									<component :is="fileIcon(file)" :size="24" />
									<span>
										<strong>{{ file.name }}</strong>
										<small>{{ file.isFolder ? t('empleados', 'Folder') : file.mimeLabel }}</small>
									</span>
								</button>
							</td>
							<td :data-label="t('empleados', 'Size')">
								{{ file.isFolder ? '—' : formatSize(file.size) }}
							</td>
							<td :data-label="t('empleados', 'Modified')">
								<span :title="formatFullDate(file.modified)">{{ formatDate(file.modified) }}</span>
							</td>
							<td class="file-actions">
								<NcActions :aria-label="t('empleados', 'Actions for {name}', { name: file.name })">
									<NcActionButton close-after-click @click="openItem(file)">
										<template #icon>
											<FolderOutline v-if="file.isFolder" :size="20" />
											<OpenInNew v-else :size="20" />
										</template>
										{{ file.isFolder ? t('empleados', 'Open folder') : t('empleados', 'Open') }}
									</NcActionButton>
									<NcActionButton v-if="!file.isFolder" close-after-click @click="downloadFile(file)">
										<template #icon>
											<Download :size="20" />
										</template>
										{{ t('empleados', 'Download') }}
									</NcActionButton>
									<NcActionButton close-after-click @click="openItemInFiles(file)">
										<template #icon>
											<OpenInNew :size="20" />
										</template>
										{{ t('empleados', 'Show in Nextcloud Files') }}
									</NcActionButton>
									<NcActionButton close-after-click @click="openRenameDialog(file)">
										<template #icon>
											<RenameBox :size="20" />
										</template>
										{{ t('empleados', 'Rename') }}
									</NcActionButton>
									<NcActionButton close-after-click @click="openDeleteDialog(file)">
										<template #icon>
											<DeleteOutline :size="20" />
										</template>
										{{ t('empleados', 'Delete') }}
									</NcActionButton>
								</NcActions>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div v-else class="central-state empty-state">
				<Magnify v-if="searchQuery" :size="48" />
				<FolderOutline v-else :size="48" />
				<strong>
					{{ searchQuery
						? t('empleados', 'No matching files')
						: t('empleados', 'This folder is empty') }}
				</strong>
				<span>
					{{ searchQuery
						? t('empleados', 'Try another search term.')
						: t('empleados', 'Upload files or create a folder to organize the employee record.') }}
				</span>
				<NcButton v-if="searchQuery" type="tertiary" @click="searchQuery = ''">
					{{ t('empleados', 'Clear search') }}
				</NcButton>
				<NcButton v-else type="primary" @click="chooseFiles">
					<template #icon>
						<CloudUpload :size="20" />
					</template>
					{{ t('empleados', 'Upload files') }}
				</NcButton>
			</div>
		</div>

		<NcDialog :open.sync="showCreateFolderDialog"
			is-form
			:buttons="createFolderButtons"
			:name="t('empleados', 'Create folder')"
			@submit="createFolder">
			<NcTextField v-model="folderName"
				:label="t('empleados', 'Folder name')"
				:error="Boolean(nameError)"
				:helper-text="nameError"
				:disabled="isMutating"
				autofocus
				required />
		</NcDialog>

		<NcDialog :open.sync="showRenameDialog"
			is-form
			:buttons="renameButtons"
			:name="t('empleados', 'Rename {name}', { name: selectedFile ? selectedFile.name : '' })"
			@submit="renameItem">
			<NcTextField v-model="newName"
				:label="t('empleados', 'New name')"
				:error="Boolean(nameError)"
				:helper-text="nameError"
				:disabled="isMutating"
				autofocus
				required />
		</NcDialog>

		<NcDialog :open.sync="showDeleteDialog"
			:name="t('empleados', 'Delete {name}?', { name: selectedFile ? selectedFile.name : '' })"
			:message="deleteMessage"
			:buttons="deleteButtons" />
	</section>
</template>

<script>
import ArchiveOutline from 'vue-material-design-icons/ArchiveOutline.vue'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import CloudUpload from 'vue-material-design-icons/CloudUpload.vue'
import DeleteOutline from 'vue-material-design-icons/DeleteOutline.vue'
import Download from 'vue-material-design-icons/Download.vue'
import FileExcelBox from 'vue-material-design-icons/FileExcelBox.vue'
import FileOutline from 'vue-material-design-icons/FileOutline.vue'
import FilePdfBox from 'vue-material-design-icons/FilePdfBox.vue'
import FilePowerpointBox from 'vue-material-design-icons/FilePowerpointBox.vue'
import FileWordBox from 'vue-material-design-icons/FileWordBox.vue'
import FolderOutline from 'vue-material-design-icons/FolderOutline.vue'
import FolderPlusOutline from 'vue-material-design-icons/FolderPlusOutline.vue'
import HomeOutline from 'vue-material-design-icons/HomeOutline.vue'
import ImageIcon from 'vue-material-design-icons/Image.vue'
import InformationOutline from 'vue-material-design-icons/InformationOutline.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import Reload from 'vue-material-design-icons/Reload.vue'
import RenameBox from 'vue-material-design-icons/RenameBox.vue'
import SortAscending from 'vue-material-design-icons/SortAscending.vue'
import SortDescending from 'vue-material-design-icons/SortDescending.vue'

import { getClient, defaultRootPath } from '@nextcloud/files/dav'
import { upload } from '@nextcloud/upload'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import {
	NcActionButton,
	NcActions,
	NcButton,
	NcDialog,
	NcLoadingIcon,
	NcTextField,
} from '@nextcloud/vue'

const OFFICE_EXTENSIONS = {
	doc: FileWordBox,
	docx: FileWordBox,
	odt: FileWordBox,
	xls: FileExcelBox,
	xlsx: FileExcelBox,
	ods: FileExcelBox,
	ppt: FilePowerpointBox,
	pptx: FilePowerpointBox,
	odp: FilePowerpointBox,
}

export default {
	name: 'FilesTab',
	components: {
		ArchiveOutline,
		ArrowLeft,
		ChevronRight,
		CloudUpload,
		DeleteOutline,
		Download,
		FileExcelBox,
		FileOutline,
		FilePdfBox,
		FilePowerpointBox,
		FileWordBox,
		FolderOutline,
		FolderPlusOutline,
		HomeOutline,
		ImageIcon,
		InformationOutline,
		Magnify,
		NcActionButton,
		NcActions,
		NcButton,
		NcDialog,
		NcLoadingIcon,
		NcTextField,
		OpenInNew,
		Reload,
		RenameBox,
		SortAscending,
		SortDescending,
	},
	props: {
		data: { type: Object, required: true },
		show: { type: Boolean, required: true },
		empleados: { type: Array, required: true },
	},
	data() {
		return {
			client: getClient(),
			files: [],
			rootPath: '',
			currentPath: '',
			navigationStack: [],
			searchQuery: '',
			sortKey: 'name',
			sortDirection: 'asc',
			isLoading: false,
			isUploading: false,
			isMutating: false,
			dragActive: false,
			loadError: '',
			fetchSequence: 0,
			uploadProgress: { done: 0, total: 0, current: '' },
			showCreateFolderDialog: false,
			showRenameDialog: false,
			showDeleteDialog: false,
			folderName: '',
			newName: '',
			nameError: '',
			selectedFile: null,
		}
	},
	computed: {
		employeeName() {
			return this.data.displayname?.trim() || this.data.uid || t('empleados', 'Employee')
		},
		isBusy() {
			return this.isLoading || this.isUploading || this.isMutating
		},
		isAtRoot() {
			return !this.rootPath || this.normalizePath(this.currentPath) === this.normalizePath(this.rootPath)
		},
		breadcrumbs() {
			if (!this.rootPath) return []
			const relativePath = this.normalizePath(this.currentPath)
				.slice(this.normalizePath(this.rootPath).length)
			const segments = relativePath.split('/').filter(Boolean)
			const crumbs = [{
				label: t('empleados', 'Employee folder'),
				path: this.rootPath,
			}]
			let path = this.rootPath
			for (const segment of segments) {
				path = this.joinPath(path, segment, true)
				crumbs.push({ label: segment, path })
			}
			return crumbs
		},
		visibleFiles() {
			const query = this.searchQuery.trim().toLocaleLowerCase()
			const filtered = query
				? this.files.filter(file => file.name.toLocaleLowerCase().includes(query))
				: [...this.files]
			const direction = this.sortDirection === 'asc' ? 1 : -1
			return filtered.sort((a, b) => {
				if (a.isFolder !== b.isFolder) return a.isFolder ? -1 : 1
				let comparison = 0
				if (this.sortKey === 'size') {
					comparison = a.size - b.size
				} else if (this.sortKey === 'modified') {
					comparison = new Date(a.modified || 0) - new Date(b.modified || 0)
				} else {
					comparison = a.name.localeCompare(b.name, undefined, {
						numeric: true,
						sensitivity: 'base',
					})
				}
				return comparison * direction
			})
		},
		folderSummary() {
			const folders = this.files.filter(file => file.isFolder).length
			const fileCount = this.files.length - folders
			return t('empleados', '{folders} folders · {files} files · {size}', {
				folders,
				files: fileCount,
				size: this.formatSize(this.files.reduce((total, file) => total + (file.isFolder ? 0 : file.size), 0)),
			})
		},
		uploadPercentage() {
			if (!this.uploadProgress.total) return 0
			return Math.round((this.uploadProgress.done / this.uploadProgress.total) * 100)
		},
		createFolderButtons() {
			return [
				{
					label: t('empleados', 'Cancel'),
					callback: () => { this.showCreateFolderDialog = false },
				},
				{
					label: t('empleados', 'Create'),
					type: 'primary',
					nativeType: 'submit',
					disabled: this.isMutating,
				},
			]
		},
		renameButtons() {
			return [
				{
					label: t('empleados', 'Cancel'),
					callback: () => { this.showRenameDialog = false },
				},
				{
					label: t('empleados', 'Rename'),
					type: 'primary',
					nativeType: 'submit',
					disabled: this.isMutating,
				},
			]
		},
		deleteButtons() {
			return [
				{
					label: t('empleados', 'Cancel'),
					callback: () => { this.showDeleteDialog = false },
				},
				{
					label: t('empleados', 'Delete'),
					type: 'error',
					disabled: this.isMutating,
					callback: this.deleteItem,
				},
			]
		},
		deleteMessage() {
			if (!this.selectedFile) return ''
			return this.selectedFile.isFolder
				? t('empleados', 'The folder and all its contents will be permanently deleted.')
				: t('empleados', 'This file will be permanently deleted.')
		},
	},
	watch: {
		data: {
			immediate: true,
			handler(newData) {
				if (newData?.uid) this.initializeEmployeeFolder()
			},
		},
	},
	methods: {
		t,
		async initializeEmployeeFolder() {
			const name = this.data.displayname?.trim() || this.data.uid
			this.rootPath = this.normalizePath(
				`${defaultRootPath}/EMPLEADOS/${this.data.uid} - ${name.toUpperCase()}`,
			)
			this.currentPath = this.rootPath
			this.navigationStack = []
			this.searchQuery = ''
			await this.fetchFiles(true)
		},
		async fetchFiles(ensureFolder = false) {
			if (!this.currentPath || !this.isInsideRoot(this.currentPath)) return
			const sequence = ++this.fetchSequence
			this.isLoading = true
			this.loadError = ''
			try {
				let response
				try {
					response = await this.client.getDirectoryContents(this.currentPath, { details: true })
				} catch (error) {
					if (!ensureFolder || error?.status !== 404) throw error
					await this.client.createDirectory(this.rootPath, { recursive: true })
					response = await this.client.getDirectoryContents(this.currentPath, { details: true })
				}
				if (sequence !== this.fetchSequence) return
				const items = Array.isArray(response.data) ? response.data : response
				this.files = Array.isArray(items)
					? items.map(file => this.mapDavFile(file))
					: []
			} catch (error) {
				if (sequence !== this.fetchSequence) return
				this.files = []
				this.loadError = this.errorMessage(error)
			} finally {
				if (sequence === this.fetchSequence) this.isLoading = false
			}
		},
		mapDavFile(file) {
			const name = file.basename || file.name || ''
			const path = file.filename || this.joinPath(this.currentPath, name, file.type === 'directory')
			const extension = this.getExtension(name)
			const fileId = Number.parseInt(file.props?.fileid || file.props?.['oc:fileid'] || file.id, 10)
			return {
				id: Number.isInteger(fileId) && fileId > 0 ? fileId : file.etag || path,
				fileId: Number.isInteger(fileId) && fileId > 0 ? fileId : null,
				name,
				path,
				size: Number(file.size) || 0,
				isFolder: file.type === 'directory',
				modified: file.lastmod || null,
				mime: file.mime || '',
				mimeLabel: this.getMimeLabel(file.mime, extension),
				extension,
			}
		},
		openItem(file) {
			if (file.isFolder) {
				this.navigationStack.push(this.currentPath)
				this.currentPath = this.normalizePath(file.path)
				this.searchQuery = ''
				this.fetchFiles()
				return
			}

			// Let Nextcloud select the registered default action for the MIME type.
			// This launches OnlyOffice/Nextcloud Office, Viewer or another installed
			// handler and falls back to Files when no compatible action is available.
			if (file.fileId) {
				this.openUrl(`${generateUrl('/f/{fileId}', { fileId: file.fileId })}?openfile=true`)
				return
			}

			this.openItemInFiles(file, true)
		},
		openItemInFiles(file, openFile = false) {
			if (file?.isFolder) {
				this.openFilesPath(this.getCleanPath(file.path))
				return
			}

			if (file?.fileId) {
				const url = generateUrl('/apps/files/files/{fileId}', { fileId: file.fileId })
				this.openUrl(`${url}?openfile=${openFile ? 'true' : 'false'}`)
				return
			}

			this.openFilesPath(this.getCleanPath(this.currentPath), file?.name)
		},
		navigateTo(path) {
			if (!this.isInsideRoot(path) || this.normalizePath(path) === this.normalizePath(this.currentPath)) return
			this.currentPath = this.normalizePath(path)
			this.navigationStack = []
			this.searchQuery = ''
			this.fetchFiles()
		},
		goBack() {
			if (this.isAtRoot) return
			const previousPath = this.navigationStack.pop()
			if (previousPath && this.isInsideRoot(previousPath)) {
				this.currentPath = this.normalizePath(previousPath)
			} else {
				const relative = this.normalizePath(this.currentPath)
					.slice(this.normalizePath(this.rootPath).length)
					.split('/')
					.filter(Boolean)
				relative.pop()
				this.currentPath = relative.reduce(
					(path, segment) => this.joinPath(path, segment, true),
					this.rootPath,
				)
			}
			this.searchQuery = ''
			this.fetchFiles()
		},
		goHome() {
			this.navigateTo(this.rootPath)
		},
		chooseFiles() {
			this.$refs.fileInput?.click()
		},
		async uploadFile(event) {
			const files = Array.from(event.target.files || [])
			await this.uploadFiles(files)
			event.target.value = ''
		},
		async handleDrop(event) {
			this.dragActive = false
			const files = Array.from(event.dataTransfer?.files || [])
			await this.uploadFiles(files)
		},
		handleDragLeave(event) {
			if (!event.currentTarget.contains(event.relatedTarget)) this.dragActive = false
		},
		async uploadFiles(files) {
			if (!files.length || this.isBusy) return
			this.isUploading = true
			this.uploadProgress = { done: 0, total: files.length, current: '' }
			const destination = this.toUploadPath(this.currentPath)
			const failures = []
			for (const file of files) {
				this.uploadProgress.current = file.name
				try {
					await upload(`${destination}${file.name}`, file)
					this.uploadProgress.done++
				} catch (error) {
					failures.push(file.name)
					showError(t('empleados', 'Could not upload {name}: {error}', {
						name: file.name,
						error: this.errorMessage(error),
					}))
				}
			}
			this.isUploading = false
			await this.fetchFiles()
			if (!failures.length) {
				showSuccess(t('empleados', '{count} files uploaded successfully.', { count: files.length }))
			} else if (failures.length < files.length) {
				showWarning(t('empleados', '{count} files could not be uploaded.', { count: failures.length }))
			}
		},
		openCreateFolderDialog() {
			this.folderName = ''
			this.nameError = ''
			this.showCreateFolderDialog = true
		},
		async createFolder() {
			const name = this.validateName(this.folderName)
			if (!name) return
			this.isMutating = true
			try {
				await this.client.createDirectory(this.joinPath(this.currentPath, name, true))
				this.showCreateFolderDialog = false
				showSuccess(t('empleados', 'Folder created.'))
				await this.fetchFiles()
			} catch (error) {
				showError(t('empleados', 'Could not create the folder: {error}', {
					error: this.errorMessage(error),
				}))
			} finally {
				this.isMutating = false
			}
		},
		openRenameDialog(file) {
			this.selectedFile = file
			this.newName = file.name
			this.nameError = ''
			this.showRenameDialog = true
		},
		async renameItem() {
			if (!this.selectedFile) return
			const name = this.validateName(this.newName)
			if (!name) return
			if (name === this.selectedFile.name) {
				this.showRenameDialog = false
				return
			}
			this.isMutating = true
			try {
				const destination = this.joinPath(this.currentPath, name, this.selectedFile.isFolder)
				await this.client.moveFile(this.selectedFile.path, destination, { overwrite: false })
				this.showRenameDialog = false
				showSuccess(t('empleados', 'Item renamed.'))
				await this.fetchFiles()
			} catch (error) {
				showError(t('empleados', 'Could not rename the item: {error}', {
					error: this.errorMessage(error),
				}))
			} finally {
				this.isMutating = false
			}
		},
		openDeleteDialog(file) {
			this.selectedFile = file
			this.showDeleteDialog = true
		},
		async deleteItem() {
			if (!this.selectedFile || this.isMutating) return
			this.isMutating = true
			try {
				await this.client.deleteFile(this.selectedFile.path)
				this.showDeleteDialog = false
				showSuccess(t('empleados', 'Item deleted.'))
				await this.fetchFiles()
			} catch (error) {
				showError(t('empleados', 'Could not delete the item: {error}', {
					error: this.errorMessage(error),
				}))
			} finally {
				this.isMutating = false
			}
		},
		downloadFile(file) {
			const link = document.createElement('a')
			link.href = this.client.getFileDownloadLink(file.path)
			link.download = file.name
			link.rel = 'noopener'
			document.body.appendChild(link)
			link.click()
			link.remove()
		},
		openInFiles() {
			this.openFilesPath(this.getCleanPath(this.currentPath))
		},
		openFilesPath(path, scrollTo = '') {
			const params = new URLSearchParams({ dir: path })
			if (scrollTo) params.set('scrollto', scrollTo)
			this.openUrl(`${generateUrl('/apps/files')}?${params.toString()}`)
		},
		openUrl(url) {
			const openedWindow = window.open(url, '_blank')
			if (openedWindow) {
				openedWindow.opener = null
				return
			}

			showWarning(t('empleados', 'The file will open in this tab because the browser blocked the new window.'))
			window.location.assign(url)
		},
		setSort(key) {
			if (this.sortKey === key) {
				this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc'
			} else {
				this.sortKey = key
				this.sortDirection = 'asc'
			}
		},
		sortIcon() {
			return this.sortDirection === 'asc' ? SortAscending : SortDescending
		},
		fileIcon(file) {
			if (file.isFolder) return FolderOutline
			if (file.extension === 'pdf') return FilePdfBox
			if (file.mime.startsWith('image/')) return ImageIcon
			if (OFFICE_EXTENSIONS[file.extension]) return OFFICE_EXTENSIONS[file.extension]
			if (['zip', 'rar', '7z', 'tar', 'gz'].includes(file.extension)) return ArchiveOutline
			return FileOutline
		},
		getMimeLabel(mime, extension) {
			if (mime?.startsWith('image/')) return t('empleados', 'Image')
			if (mime?.startsWith('video/')) return t('empleados', 'Video')
			if (mime?.startsWith('audio/')) return t('empleados', 'Audio')
			if (extension) return extension.toUpperCase()
			return t('empleados', 'File')
		},
		getExtension(name) {
			const parts = name.toLocaleLowerCase().split('.')
			return parts.length > 1 ? parts.pop() : ''
		},
		validateName(value) {
			const name = value.trim()
			if (!name) {
				this.nameError = t('empleados', 'Enter a name.')
				return ''
			}
			if (name === '.' || name === '..' || /[\\/:*?"<>|]/.test(name)) {
				this.nameError = t('empleados', 'The name contains invalid characters.')
				return ''
			}
			if (this.files.some(file => file.name.toLocaleLowerCase() === name.toLocaleLowerCase()
				&& file !== this.selectedFile)) {
				this.nameError = t('empleados', 'An item with this name already exists.')
				return ''
			}
			this.nameError = ''
			return name
		},
		normalizePath(path) {
			const normalized = `/${String(path || '').split('/').filter(Boolean).join('/')}/`
			return normalized === '//' ? '/' : normalized
		},
		joinPath(base, name, isFolder = false) {
			const path = `${this.normalizePath(base)}${name}`
			return isFolder ? this.normalizePath(path) : path
		},
		isInsideRoot(path) {
			return Boolean(this.rootPath)
				&& this.normalizePath(path).startsWith(this.normalizePath(this.rootPath))
		},
		toUploadPath(path) {
			const cleanPath = this.getCleanPath(path)
			return cleanPath.endsWith('/') ? cleanPath : `${cleanPath}/`
		},
		getCleanPath(path) {
			const rootSegments = defaultRootPath.split('/').filter(Boolean)
			const pathSegments = path.split('/').filter(Boolean)
			return `/${pathSegments.slice(rootSegments.length).join('/')}/`
		},
		formatSize(bytes) {
			const size = Number(bytes) || 0
			if (!size) return '0 B'
			const units = ['B', 'KB', 'MB', 'GB', 'TB']
			const index = Math.min(Math.floor(Math.log(size) / Math.log(1024)), units.length - 1)
			const value = size / (1024 ** index)
			return `${new Intl.NumberFormat(undefined, {
				maximumFractionDigits: index === 0 ? 0 : 1,
			}).format(value)} ${units[index]}`
		},
		formatDate(date) {
			if (!date) return '—'
			const parsed = new Date(date)
			if (Number.isNaN(parsed.getTime())) return '—'
			return new Intl.DateTimeFormat(undefined, {
				dateStyle: 'medium',
				timeStyle: 'short',
			}).format(parsed)
		},
		formatFullDate(date) {
			if (!date) return ''
			const parsed = new Date(date)
			if (Number.isNaN(parsed.getTime())) return ''
			return new Intl.DateTimeFormat(undefined, {
				dateStyle: 'full',
				timeStyle: 'medium',
			}).format(parsed)
		},
		errorMessage(error) {
			if (error?.status === 403) return t('empleados', 'You do not have permission to perform this action.')
			if (error?.status === 404) return t('empleados', 'The item no longer exists.')
			if (error?.status === 409 || error?.status === 412) {
				return t('empleados', 'An item with this name already exists.')
			}
			return error?.message || t('empleados', 'Unexpected server error.')
		},
	},
}
</script>

<style scoped>
.employee-files {
	margin-top: 16px;
	color: var(--color-main-text);
}

.files-header,
.navigation-bar,
.file-toolbar,
.folder-summary,
.upload-status {
	display: flex;
	align-items: center;
}

.files-header {
	justify-content: space-between;
	gap: 16px;
	padding: 4px 4px 16px;
}

.files-header h2,
.files-header p {
	margin: 0;
}

.files-header h2 {
	font-size: 20px;
	font-weight: 700;
}

.files-header p {
	margin-top: 2px;
	color: var(--color-text-maxcontrast);
}

.navigation-bar {
	gap: 8px;
	min-height: 48px;
	padding: 4px 8px;
	border: 1px solid var(--color-border);
	border-bottom: 0;
	border-radius: var(--border-radius-large) var(--border-radius-large) 0 0;
	background: var(--color-background-dark);
}

.navigation-controls {
	display: flex;
	flex: 0 0 auto;
	gap: 2px;
	padding-right: 6px;
	border-right: 1px solid var(--color-border);
}

.breadcrumbs {
	display: flex;
	align-items: center;
	min-width: 0;
	overflow-x: auto;
	scrollbar-width: thin;
}

.breadcrumb {
	display: inline-flex;
	align-items: center;
	flex: 0 0 auto;
	gap: 4px;
	min-height: 36px;
	padding: 0 6px;
	border: 0;
	border-radius: var(--border-radius);
	background: transparent;
	color: var(--color-text-maxcontrast);
	cursor: pointer;
}

.breadcrumb:hover:not(:disabled) {
	background: var(--color-background-hover);
	color: var(--color-main-text);
}

.breadcrumb.current {
	color: var(--color-main-text);
	font-weight: 700;
	cursor: default;
}

.file-toolbar {
	justify-content: space-between;
	gap: 16px;
	padding: 12px;
	border: 1px solid var(--color-border);
	background: var(--color-main-background);
}

.primary-actions {
	display: flex;
	align-items: center;
	gap: 8px;
}

.files-search {
	width: min(340px, 100%);
	margin: 0;
}

.upload-status {
	position: relative;
	gap: 12px;
	padding: 12px 16px 16px;
	border-right: 1px solid var(--color-border);
	border-left: 1px solid var(--color-border);
	background: var(--color-primary-element-light);
}

.upload-status > div:not(.progress-track) {
	display: flex;
	flex-direction: column;
	min-width: 0;
}

.upload-status span {
	overflow: hidden;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.progress-track {
	position: absolute;
	right: 0;
	bottom: 0;
	left: 0;
	height: 4px;
	background: var(--color-border);
}

.progress-track span {
	display: block;
	height: 100%;
	background: var(--color-primary-element);
	transition: width 180ms ease;
}

.folder-summary {
	justify-content: space-between;
	gap: 12px;
	padding: 8px 14px;
	border-right: 1px solid var(--color-border);
	border-left: 1px solid var(--color-border);
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.browser-surface {
	position: relative;
	min-height: 320px;
	overflow: hidden;
	border: 1px solid var(--color-border);
	border-radius: 0 0 var(--border-radius-large) var(--border-radius-large);
	background: var(--color-main-background);
}

.browser-surface.is-dragging {
	border-color: var(--color-primary-element);
}

.drop-overlay {
	position: absolute;
	z-index: 20;
	inset: 8px;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-direction: column;
	gap: 8px;
	border: 2px dashed var(--color-primary-element);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background-translucent);
	color: var(--color-primary-element);
	pointer-events: none;
	backdrop-filter: blur(4px);
}

.drop-overlay span {
	color: var(--color-text-maxcontrast);
}

.file-table-wrap {
	overflow-x: auto;
}

.file-table {
	width: 100%;
	border-collapse: collapse;
	table-layout: fixed;
}

.file-table th,
.file-table td {
	height: 58px;
	padding: 6px 14px;
	border-bottom: 1px solid var(--color-border);
	text-align: left;
	vertical-align: middle;
}

.file-table th {
	height: 42px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	font-weight: 600;
}

.file-table th:first-child {
	width: auto;
}

.file-table th:nth-child(2) {
	width: 110px;
}

.file-table th:nth-child(3) {
	width: 210px;
}

.file-table th:last-child {
	width: 56px;
}

.file-table tbody tr:last-child td {
	border-bottom: 0;
}

.file-table tbody tr:hover {
	background: var(--color-background-hover);
}

.sort-button {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 5px;
	border: 0;
	border-radius: var(--border-radius);
	background: transparent;
	color: inherit;
	font: inherit;
	cursor: pointer;
}

.sort-button:hover {
	background: var(--color-background-hover);
	color: var(--color-main-text);
}

.file-identity {
	display: flex;
	align-items: center;
	gap: 12px;
	width: 100%;
	min-width: 0;
	padding: 5px;
	border: 0;
	border-radius: var(--border-radius);
	background: transparent;
	color: var(--color-primary-element);
	text-align: left;
	cursor: pointer;
}

.file-identity > span {
	display: flex;
	flex-direction: column;
	min-width: 0;
}

.file-identity strong,
.file-identity small {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.file-identity strong {
	color: var(--color-main-text);
	font-weight: 600;
}

.file-identity small {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 400;
}

.file-actions {
	text-align: right !important;
}

.central-state {
	display: flex;
	align-items: center;
	justify-content: center;
	flex-direction: column;
	gap: 10px;
	min-height: 320px;
	padding: 40px 20px;
	color: var(--color-text-maxcontrast);
	text-align: center;
}

.central-state strong {
	color: var(--color-main-text);
	font-size: 17px;
}

.central-state span {
	max-width: 520px;
}

.error-state {
	color: var(--color-error);
}

.file-identity:hover strong {
	text-decoration: underline;
}

.hidden-file-input,
.hidden-visually {
	position: absolute !important;
	width: 1px !important;
	height: 1px !important;
	padding: 0 !important;
	overflow: hidden !important;
	clip: rect(0, 0, 0, 0) !important;
	white-space: nowrap !important;
	border: 0 !important;
}

@media (max-width: 760px) {
	.files-header {
		align-items: flex-start;
		flex-direction: column;
	}

	.navigation-bar {
		align-items: stretch;
		flex-direction: column;
	}

	.navigation-controls {
		border-right: 0;
	}

	.file-toolbar {
		align-items: stretch;
		flex-direction: column;
	}

	.primary-actions {
		flex-wrap: wrap;
	}

	.files-search {
		width: 100%;
	}

	.folder-summary {
		align-items: flex-start;
		flex-direction: column;
	}

	.file-table,
	.file-table tbody,
	.file-table tr,
	.file-table td {
		display: block;
	}

	.file-table thead {
		display: none;
	}

	.file-table tr {
		position: relative;
		padding: 8px 54px 8px 8px;
		border-bottom: 1px solid var(--color-border);
	}

	.file-table td {
		height: auto;
		padding: 3px 8px;
		border: 0;
	}

	.file-table td:nth-child(2),
	.file-table td:nth-child(3) {
		padding-left: 44px;
		color: var(--color-text-maxcontrast);
		font-size: 12px;
	}

	.file-table td:nth-child(2)::before,
	.file-table td:nth-child(3)::before {
		content: attr(data-label) ': ';
		font-weight: 600;
	}

	.file-actions {
		position: absolute;
		top: 12px;
		right: 8px;
		padding: 0 !important;
	}
}
</style>
